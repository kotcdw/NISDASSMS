<?php
declare(strict_types=1);

/** Installable phone app: web app manifest, service worker, offline page, push subscriptions, Google Play link file. */
class PwaController extends Controller
{
    public function manifest(): void
    {
        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: max-age=3600');
        $base = rtrim(app_base(), '/') . '/';
        echo json_encode([
            'id' => $base, 'name' => school_name(), 'short_name' => setting('app_short_name', 'NISDAS'),
            'description' => 'School management for ' . school_name() . ': attendance, results, fees, homework and messages.',
            'start_url' => $base . '?source=app', 'scope' => $base, 'display' => 'standalone', 'orientation' => 'portrait',
            'background_color' => '#ffffff', 'theme_color' => app_theme_color(), 'lang' => 'en-GH', 'categories' => ['education'],
            'icons' => [
                ['src' => asset('favicon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('icon-maskable-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => asset('icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                ['name' => 'Notifications', 'url' => $base . 'notifications'],
                ['name' => 'Dashboard', 'url' => $base . 'dashboard'],
                ['name' => 'My children (parents)', 'url' => $base . 'portal'],
                ['name' => 'NISDAS APPS', 'url' => $base . 'modules'],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function worker(): void
    {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Cache-Control: no-cache');
        header('Service-Worker-Allowed: ' . rtrim(app_base(), '/') . '/');
        $offline = url('offline');
        $static = json_encode([asset('css/app.css'), asset('js/app.js'), asset('favicon-192.png'), $offline], JSON_UNESCAPED_SLASHES);
        $v = substr(md5((string) @filemtime(BASE_PATH . '/assets/css/app.css') . (string) @filemtime(BASE_PATH . '/assets/js/app.js')), 0, 8);
        echo <<<JS
// NISDAS SMS service worker: keeps the app shell for offline use and shows notifications.
const CACHE = 'nisdas-$v';
const STATIC = $static;
self.addEventListener('install', e => { e.waitUntil(caches.open(CACHE).then(c => c.addAll(STATIC)).then(() => self.skipWaiting())); });
self.addEventListener('activate', e => { e.waitUntil(caches.keys().then(ks => Promise.all(ks.filter(k => k !== CACHE).map(k => caches.delete(k)))).then(() => self.clients.claim())); });
self.addEventListener('fetch', e => {
  const r = e.request;
  if (r.method !== 'GET' || new URL(r.url).origin !== location.origin) return;
  if (r.mode === 'navigate') {
    // Pages: always fresh from the server; the last copy of each page is kept for when there is no network
    e.respondWith(fetch(r).then(res => { if (res.ok && !res.redirected) { const c = res.clone(); caches.open(CACHE).then(k => k.put(r, c)); } return res; })
      .catch(() => caches.match(r).then(m => m || caches.match('$offline'))));
    return;
  }
  if (/\.(css|js|png|svg|ico|woff2?)(\?|$)/.test(r.url)) {
    e.respondWith(caches.match(r).then(m => m || fetch(r).then(res => { const c = res.clone(); caches.open(CACHE).then(k => k.put(r, c)); return res; })));
  }
});
self.addEventListener('push', e => {
  let d = {}; try { d = e.data ? e.data.json() : {}; } catch (x) { d = { title: e.data ? e.data.text() : 'NISDAS' }; }
  e.waitUntil(self.registration.showNotification(d.title || 'NISDAS', { body: d.body || '', tag: d.tag, icon: STATIC[2], badge: STATIC[2], data: { url: d.url || './' } }));
});
self.addEventListener('notificationclick', e => {
  e.notification.close();
  const u = (e.notification.data && e.notification.data.url) || './';
  e.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then(ws => { for (const w of ws) { if ('focus' in w) { w.navigate(u); return w.focus(); } } return clients.openWindow(u); }));
});
JS;
        exit;
    }

    public function offline(): void
    {
        $this->view('pwa/offline', ['title' => 'No connection'], 'layouts/public');
    }

    public function key(): void
    {
        header('Content-Type: application/json');
        echo json_encode(['key' => WebPush::ready() ? WebPush::publicKey() : null]);
        exit;
    }

    public function subscribe(): void
    {
        $j = json_decode((string) file_get_contents('php://input'), true) ?: [];
        $ep = (string) ($j['endpoint'] ?? '');
        $p = (string) ($j['keys']['p256dh'] ?? '');
        $a = (string) ($j['keys']['auth'] ?? '');
        header('Content-Type: application/json');
        if (!WebPush::ready() || !preg_match('#^https://#', $ep) || strlen($ep) > 2000 || strlen(WebPush::unb64u($p)) !== 65 || strlen(WebPush::unb64u($a)) < 8) {
            http_response_code(422);
            echo json_encode(['ok' => false]);
            exit;
        }
        db()->run(
            'INSERT INTO push_subscriptions (user_id, endpoint, endpoint_hash, p256dh, auth, user_agent) VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), p256dh = VALUES(p256dh), auth = VALUES(auth), user_agent = VALUES(user_agent)',
            [Auth::id(), $ep, hash('sha256', $ep), $p, $a, mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 190)]
        );
        audit('push_subscribed', 'users', Auth::id());
        echo json_encode(['ok' => true]);
        exit;
    }

    public function unsubscribe(): void
    {
        $j = json_decode((string) file_get_contents('php://input'), true) ?: [];
        db()->run('DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint_hash = ?', [Auth::id(), hash('sha256', (string) ($j['endpoint'] ?? ''))]);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    /** Send a test notification to this person's devices. */
    public function test(): void
    {
        Notifier::inapp([(int) Auth::id()], 'Test notification', 'Phone notifications are working.', 'notifications');
        $n = (int) db()->value('SELECT COUNT(*) FROM push_subscriptions WHERE user_id = ?', [Auth::id()]);
        flash($n ? 'success' : 'warning', $n ? 'Test sent to ' . $n . ' device(s).' : 'This account has no device with notifications turned on yet.');
        redirect('notifications');
    }

    /** Digital Asset Links for the Google Play (Trusted Web Activity) app. */
    public function assetlinks(): void
    {
        header('Content-Type: application/json');
        $pkg = setting('play_package');
        $fps = array_values(array_filter(array_map('trim', explode(',', setting('play_sha256')))));
        echo json_encode($pkg && $fps ? [['relation' => ['delegate_permission/common.handle_all_urls'], 'target' => ['namespace' => 'android_app', 'package_name' => $pkg, 'sha256_cert_fingerprints' => $fps]]] : [], JSON_UNESCAPED_SLASHES);
        exit;
    }

    public const THEMES = ['#1b2f86' => 'School blue', '#121f5c' => 'Navy', '#0d9488' => 'Teal', '#1f7a4d' => 'Green', '#7a1f3d' => 'Maroon', '#5b21b6' => 'Purple'];

    public function saveApp(): void
    {
        $pkg = trim((string) ($_POST['play_package'] ?? ''));
        $fp = strtoupper(trim((string) ($_POST['play_sha256'] ?? '')));
        $short = mb_substr(trim((string) ($_POST['app_short_name'] ?? '')), 0, 12);
        $theme = (string) ($_POST['app_theme_color'] ?? '');
        if (($pkg !== '' && !preg_match('/^[a-z][a-z0-9_]*(\.[a-z0-9_]+)+$/', $pkg)) || ($fp !== '' && !preg_match('/^([0-9A-F]{2}:){31}[0-9A-F]{2}(,\s*([0-9A-F]{2}:){31}[0-9A-F]{2})*$/', $fp))
            || ($theme !== '' && !isset(self::THEMES[$theme]))) {
            flash('error', 'Check the package name (like academy.nisdasms.app) and the SHA-256 fingerprint (32 pairs like AB:CD:…).');
            redirect('setup/app', ['tab' => 'settings']);
        }
        setting_set('play_package', $pkg);
        setting_set('play_sha256', $fp);
        if ($short !== '') {
            setting_set('app_short_name', $short);
        }
        if ($theme !== '') {
            setting_set('app_theme_color', $theme);
        }
        audit('app_settings_saved', 'settings');
        flash('success', 'Phone app settings saved. Phones pick up the new name and colour the next time the app is opened.');
        redirect('setup/app', ['tab' => 'settings']);
    }

    public function appSettings(): void
    {
        $ready = WebPush::ready();
        $public = rtrim(setting('public_url'), '/');
        $host = strtolower((string) parse_url($public, PHP_URL_HOST));
        $live = str_starts_with($public, 'https://') && $host !== '' && str_contains($host, '.') && !preg_match('/^(localhost|127\.|10\.|192\.168\.)/', $host);
        $cron = setting('cron_last_run');
        $cronOk = $cron !== '' && strtotime($cron . ' UTC') > time() - 3600;
        $icons = array_filter(['favicon-192.png', 'icon-512.png', 'icon-maskable-192.png', 'icon-maskable-512.png', 'apple-touch-icon.png'], fn($f) => !is_file(BASE_PATH . '/assets/' . $f));
        $checks = [
            ['Live https address set', $live, $live ? $public . ' — phones can install once the site is online there.' : ($public ? $public . ' is not a live https address yet.' : 'No Public web address is set.'), 'Set it under App settings → Fee settings → Public web address once the domain and hosting are ready.'],
            ['App files (manifest, icons, offline page)', !$icons, $icons ? 'Missing: ' . implode(', ', $icons) : 'Name, icons for Android and iPhone, offline page and 4 home-screen shortcuts.', 'Copy the assets folder again.'],
            ['Phone notification keys', $ready, $ready ? 'Created and stored safely (VAPID).' : 'Not available: update the database and check that PHP has OpenSSL.', 'Click "Update database now".'],
            ['Scheduled job (sends notifications)', $cronOk, $cronOk ? 'Last ran ' . fmt_date(date('Y-m-d H:i:s', strtotime($cron . ' UTC')), 'j M H:i') : ($cron ? 'Last ran ' . fmt_date(date('Y-m-d H:i:s', strtotime($cron . ' UTC')), 'j M H:i') . ' — more than an hour ago.' : 'Has never run on this computer.'), 'Set up bin/cron.php every 10 minutes (see README).'],
            ['Google Play link (optional)', setting('play_package') !== '' && setting('play_sha256') !== '' ? true : null, setting('play_package') ?: 'Not set up — only needed for a Play Store listing.', 'See "Google Play" below.'],
        ];
        $groups = ['staff' => 0, 'parent' => 0, 'pupil' => 0];
        $devices = [];
        $stats = ['total' => 0, 'new30' => 0, 'active30' => 0];
        if ($ready) {
            $devices = db()->all('SELECT s.id, s.user_agent, s.created_at, s.last_used_at, u.name, u.role FROM push_subscriptions s JOIN users u ON u.id = s.user_id ORDER BY s.created_at DESC LIMIT 200');
            foreach ($devices as $d) {
                $groups[in_array($d['role'], ['parent', 'pupil'], true) ? $d['role'] : 'staff']++;
            }
            $stats = db()->one('SELECT COUNT(*) AS total, SUM(created_at >= NOW() - INTERVAL 30 DAY) AS new30, SUM(last_used_at >= NOW() - INTERVAL 30 DAY) AS active30 FROM push_subscriptions');
        }
        $yid = current_year()['id'] ?? 0;
        $reach = [
            'parents' => (int) db()->value("SELECT COUNT(DISTINCT g.phone) FROM guardians g JOIN pupil_guardian pg ON pg.guardian_id = g.id JOIN enrolments e ON e.pupil_id = pg.pupil_id AND e.academic_year_id = ? AND e.status = 'active' WHERE g.phone <> ''", [$yid]),
            'staff' => (int) db()->value("SELECT COUNT(DISTINCT phone) FROM users WHERE is_active = 1 AND role NOT IN ('parent','pupil') AND phone <> ''"),
        ];
        $this->view('pwa/settings', ['title' => 'Phone app', 'checks' => $checks, 'live' => $live, 'link' => absolute_url(''), 'groups' => $groups, 'stats' => $stats,
            'devices' => $devices, 'reach' => $reach, 'mine' => $ready ? (int) db()->value('SELECT COUNT(*) FROM push_subscriptions WHERE user_id = ?', [Auth::id()]) : 0]);
    }

    /** SMS everyone in a group the link and how to install the app (once a day per group). */
    public function invite(): void
    {
        $to = (string) ($_POST['audience'] ?? '');
        if (!in_array($to, ['parents', 'staff'], true)) {
            redirect('setup/app');
        }
        if (setting('app_invite_' . $to) === date('Y-m-d')) {
            flash('warning', 'An invitation already went to the ' . $to . ' today.');
            redirect('setup/app');
        }
        $yid = current_year()['id'] ?? 0;
        $rows = $to === 'parents'
            ? db()->all("SELECT g.phone, MIN(g.name) AS name FROM guardians g JOIN pupil_guardian pg ON pg.guardian_id = g.id JOIN enrolments e ON e.pupil_id = pg.pupil_id AND e.academic_year_id = ? AND e.status = 'active' WHERE g.phone <> '' GROUP BY g.phone", [$yid])
            : db()->all("SELECT phone, MIN(name) AS name FROM users WHERE is_active = 1 AND role NOT IN ('parent','pupil') AND phone <> '' GROUP BY phone");
        $link = absolute_url('');
        $n = 0;
        foreach ($rows as $r) {
            $body = 'Get the ' . school_name() . ' app on your phone: open ' . $link . ' , sign in, then choose Install (Android) or Share > Add to Home Screen (iPhone). Turn on notifications in the bell. - ' . school_name();
            if (Notifier::sms($r['phone'], $body, ['name' => $r['name'], 'event' => 'app_invite'])) {
                $n++;
            }
        }
        setting_set('app_invite_' . $to, date('Y-m-d'));
        audit('app_invite_sent', 'settings', null, ['audience' => $to, 'count' => $n]);
        flash('success', 'Invitation queued for ' . $n . ' ' . $to . '. It goes out with the next SMS run.');
        redirect('setup/app');
    }

    /** Turn off phone notifications for one device (lost or changed phone). */
    public function removeDevice(int $id): void
    {
        db()->run('DELETE FROM push_subscriptions WHERE id = ?', [$id]);
        audit('push_device_removed', 'push_subscriptions', $id);
        flash('success', 'Device removed. It will no longer get notifications.');
        redirect('setup/app', ['tab' => 'devices']);
    }
}
