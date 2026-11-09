<?php
declare(strict_types=1);

function config(string $key, $default = null)
{
    $value = $GLOBALS['__config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

/** Escape for HTML output. */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Web path of the app folder, e.g. "/NISDASSMS" (no trailing slash). */
function app_base(): string
{
    static $base = null;
    if ($base === null) {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
        if ($base === '.') {
            $base = '';
        }
    }
    return $base;
}

function url(string $route = '', array $query = []): string
{
    $route = ltrim($route, '/');
    $prefix = config('pretty_urls', true) ? app_base() . '/' : app_base() . '/index.php/';
    $u = $prefix . $route;
    if ($query) {
        $u .= '?' . http_build_query($query);
    }
    return $u;
}

/**
 * Full link (https://host/route) for SMS, payment gateways and shared links.
 * Uses the "Public web address" setting when set (needed for links sent from cron and for go-live).
 */
function absolute_url(string $route = '', array $query = []): string
{
    $public = rtrim((string) setting('public_url', ''), '/');
    if ($public !== '' && preg_match('#^https?://#i', $public)) {
        if (is_demo()) {
            $public .= '/demo'; // the practice copy lives under /demo on the same site
        }
        $u = $public . '/' . (config('pretty_urls', true) ? '' : 'index.php/') . ltrim($route, '/');
        return $query ? $u . '?' . http_build_query($query) : $u;
    }
    $host = preg_replace('/[^a-z0-9.:-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    return (is_https() ? 'https' : 'http') . '://' . $host . url($route, $query);
}

/** Full link on the address this browser is using right now (for redirects back to this site, e.g. after paying). */
function request_url(string $route = '', array $query = []): string
{
    $host = preg_replace('/[^a-z0-9.:-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    return (is_https() ? 'https' : 'http') . '://' . $host . url($route, $query);
}

/**
 * Is the Public web address ready for links sent to parents? Returns null when fine,
 * or ['bad', message] when SMS links would not open on parents' phones.
 */
function public_url_status(): ?array
{
    $public = rtrim((string) setting('public_url', ''), '/');
    $fix = can('app.configure') ? ' Set it in Profile → App settings → Fee settings.' : ' Ask an administrator to set the Public web address.';
    if ($public === '') {
        return ['bad', 'No Public web address is set, so links in SMS point to this computer and will not open on parents\' phones.' . $fix];
    }
    $host = strtolower((string) parse_url($public, PHP_URL_HOST));
    if ($host === 'localhost' || preg_match('/^(127\.|10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/', $host) || !str_contains($host, '.')) {
        return ['bad', "The Public web address ($public) is a local address, so links in SMS will not open on parents' phones." . $fix];
    }
    return null;
}

/** Is this request on HTTPS? Behind a hosting proxy set 'trust_proxy' => true in config.local.php. */
function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }
    return config('trust_proxy', false) && strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function asset(string $path): string
{
    $file = BASE_PATH . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : 1;
    // The practice copy runs from /demo but shares the real assets folder
    $base = is_demo() ? rtrim(dirname(app_base()), '/\\') : app_base();
    return $base . '/assets/' . ltrim($path, '/') . '?v=' . $v;
}

/** True inside the practice (training) copy at /demo — separate database, no real SMS or payments. */
function is_demo(): bool
{
    return defined('DEMO_MODE') && DEMO_MODE;
}

/** Folder for uploaded documents and photos (the practice copy keeps its own). */
function uploads_dir(): string
{
    return BASE_PATH . (is_demo() ? '/storage/demo_uploads' : '/storage/uploads');
}

/** Route requested, relative to the app folder, without slashes: "admission/12". */
function current_route(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $path = rawurldecode($path);
    $base = app_base();
    if ($base !== '' && strpos($path, $base) === 0) {
        $path = substr($path, strlen($base));
    }
    $path = preg_replace('#^/index\.php#', '', $path);
    return trim((string) $path, '/');
}

function redirect(string $route, array $query = []): void
{
    header('Location: ' . url($route, $query));
    exit;
}

function back(string $fallback = 'dashboard'): void
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($ref && parse_url($ref, PHP_URL_HOST) === $host) {
        header('Location: ' . $ref);
        exit;
    }
    redirect($fallback);
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

/** Keep form input across a redirect after validation errors. */
function with_old(array $input, array $errors = []): void
{
    $_SESSION['_old'] = $input;
    $_SESSION['_errors'] = $errors;
}

function old(string $key, $default = '')
{
    return $GLOBALS['__old'][$key] ?? $default;
}

function field_error(string $key): string
{
    $msg = $GLOBALS['__errors'][$key] ?? '';
    return $msg ? '<div class="field-error">' . e($msg) . '</div>' : '';
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

function auth(): ?array
{
    return Auth::user();
}

function can(string $permission): bool
{
    return Auth::can($permission);
}

function input(string $key, $default = null)
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function db(): Database
{
    return Database::instance();
}

function fmt_date(?string $date, string $format = 'd M Y'): string
{
    if (!$date || $date === '0000-00-00') {
        return '';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '';
}

function full_name(array $row): string
{
    return trim(preg_replace('/\s+/', ' ', ($row['first_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['last_name'] ?? '')));
}

function age_from(?string $dob): string
{
    if (!$dob) {
        return '';
    }
    try {
        $d = new DateTime($dob);
        $diff = $d->diff(new DateTime('today'));
        return $diff->y > 0 ? $diff->y . ' yrs' : $diff->m . ' mths';
    } catch (Exception $e) {
        return '';
    }
}

/** The settings cache shared by setting() and setting_set() (one load per request). */
function &settings_cache(): ?array
{
    static $cache = null;
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $cache = &settings_cache();
    if ($cache === null) {
        $cache = [];
        try {
            foreach (db()->all('SELECT `key`, `value` FROM settings') as $r) {
                $cache[$r['key']] = (string) $r['value'];
            }
        } catch (Throwable $e) {
            // settings table may not exist before install
        }
    }
    return $cache[$key] ?? $default;
}

/** URL of the uploaded school logo (cache-busted when it changes). */
/** Background picture: the school's own photo (School setup) or the built-in illustration; '' when switched off. */
function bg_art_url(): string
{
    $mode = setting('bg_mode', 'school');
    if ($mode === 'off') {
        return '';
    }
    if ($mode === 'photo' && setting('bg_photo') !== '') {
        return url('bg-image', ['v' => substr(md5(setting('bg_photo')), 0, 8)]);
    }
    if ($mode === 'illustration') {
        return asset('img/children.svg');
    }
    return asset('img/school.jpg'); // built-in school photo (default)
}

/** The app's own logo (from the school uniform): sidebar, sign-in, public pages, phone icons. Printouts keep logo_url(). */
function app_logo_url(): string
{
    return asset('img/app-logo.png');
}

/** The school crest: an uploaded logo (School setup) or the official crest built into the app. */
function logo_url(): string
{
    if (setting('school_logo') === '') {
        return asset('img/crest.png');
    }
    return url('logo', ['v' => substr(md5(setting('school_logo')), 0, 8)]);
}

/** Browser-tab and home-screen icons (the official crest). */
function favicon_tags(): string
{
    return '<link rel="icon" href="' . e(asset('favicon.ico')) . '" sizes="48x48">'
        . '<link rel="icon" href="' . e(asset('favicon-32.png')) . '" type="image/png" sizes="32x32">'
        . '<link rel="icon" href="' . e(asset('favicon-192.png')) . '" type="image/png" sizes="192x192">'
        . '<link rel="apple-touch-icon" href="' . e(asset('apple-touch-icon.png')) . '">'
        // Installable app (manifest + service worker)
        . '<link rel="manifest" href="' . e(url('manifest.webmanifest')) . '"><meta name="theme-color" content="' . e(app_theme_color()) . '"><meta name="sw-url" content="' . e(url('sw.js')) . '">'
        // iPhone: open full-screen from the home screen, with the school's short name under the icon
        . '<meta name="apple-mobile-web-app-capable" content="yes"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="default">'
        . '<meta name="apple-mobile-web-app-title" content="' . e(setting('app_short_name', 'NISDAS') ?: 'NISDAS') . '">';
}

/** Colour of the phone app's top bar (Setup → Phone app). */
function app_theme_color(): string
{
    $c = setting('app_theme_color', '#1b2f86');
    return preg_match('/^#[0-9a-f]{6}$/i', $c) ? $c : '#1b2f86';
}

/** Full school name for titles and headings. */
function school_name(): string
{
    return setting('school_name', 'Naylor International S.D.A School') ?: 'Naylor International S.D.A School';
}

function setting_set(string $key, string $value): void
{
    db()->run('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)', [$key, $value]);
    $cache = &settings_cache();
    if ($cache !== null) {
        $cache[$key] = $value; // later setting() calls in this request see the new value
    }
}

function is_installed(): bool
{
    return is_demo() || is_file(BASE_PATH . '/storage/installed.lock');
}

function audit(string $action, string $entity = '', ?int $entityId = null, $details = null): void
{
    try {
        db()->insert('audit_log', [
            'user_id'   => Auth::id(),
            'action'    => $action,
            'entity'    => $entity,
            'entity_id' => $entityId,
            'details'   => $details === null ? null : (is_string($details) ? $details : json_encode($details)),
            'ip'        => PHP_SAPI === 'cli' ? null : Security::clientIp(),
        ]);
    } catch (Throwable $e) {
        error_log('[NISDAS] audit failed: ' . $e->getMessage());
    }
}

/** Current academic year row (or null). */
function current_year(): ?array
{
    static $y = false;
    if ($y === false) {
        $y = db()->one('SELECT * FROM academic_years WHERE is_current = 1 LIMIT 1');
    }
    return $y;
}

function current_term(): ?array
{
    static $t = false;
    if ($t === false) {
        $t = db()->one('SELECT t.*, y.name AS year_name FROM terms t JOIN academic_years y ON y.id = t.academic_year_id WHERE t.is_current = 1 LIMIT 1');
    }
    return $t;
}

function selected($a, $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function checked($v): string
{
    return $v ? ' checked' : '';
}

function badge(string $text, string $tone = 'neutral'): string
{
    return '<span class="badge badge-' . e($tone) . '">' . e($text) . '</span>';
}

/** Simple pagination helper — returns [rows, pager]. */
function paginate(string $sql, array $params, int $perPage = 25): array
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $total = (int) db()->value("SELECT COUNT(*) FROM ($sql) AS _c", $params);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $pages);
    $offset = ($page - 1) * $perPage;
    $rows = db()->all("$sql LIMIT $perPage OFFSET $offset", $params);
    return [$rows, ['page' => $page, 'pages' => $pages, 'total' => $total, 'per' => $perPage]];
}

function pager_links(array $pager): string
{
    if ($pager['pages'] <= 1) {
        return '';
    }
    $q = $_GET;
    $link = function (int $i, string $text, bool $active = false) use (&$q): string {
        $q['page'] = $i;
        return '<a' . ($active ? ' class="active"' : '') . ' href="?' . e(http_build_query($q)) . '">' . $text . '</a>';
    };
    $cur = (int) $pager['page'];
    $last = (int) $pager['pages'];
    $per = (int) ($pager['per'] ?? 25);
    $info = isset($pager['total']) ? '<span class="pg-info">Showing ' . (($cur - 1) * $per + 1) . '–' . min((int) $pager['total'], $cur * $per) . ' of ' . number_format((int) $pager['total']) . '</span>' : '';
    $html = '<nav class="pager" aria-label="Pages">' . $info . '<span class="pg-links">' . ($cur > 1 ? $link($cur - 1, '‹ Prev') : '');
    $prev = 0;
    foreach (range(1, $last) as $i) {
        if ($i !== 1 && $i !== $last && abs($i - $cur) > 2) {
            continue; // long lists show 1 … around the current page … last
        }
        if ($prev && $i - $prev > 1) {
            $html .= '<span class="pager-gap">…</span>';
        }
        $html .= $link($i, (string) $i, $i === $cur);
        $prev = $i;
    }
    return $html . ($cur < $last ? $link($cur + 1, 'Next ›') : '') . '</span></nav>';
}

/** A value for a CSV cell that Excel will not run as a formula (cells starting with = + - @ get a leading quote). */
function csv_safe($v): string
{
    $v = (string) ($v ?? '');
    return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
}


/** A whole number in English words ("one thousand two hundred and fifty"). */
function number_words(int $n): string
{
    $ones = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
    $tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
    if ($n < 20) {
        return $ones[$n];
    }
    if ($n < 100) {
        return $tens[intdiv($n, 10)] . ($n % 10 ? '-' . $ones[$n % 10] : '');
    }
    if ($n < 1000) {
        return $ones[intdiv($n, 100)] . ' hundred' . ($n % 100 ? ' and ' . number_words($n % 100) : '');
    }
    foreach ([1000000000 => 'billion', 1000000 => 'million', 1000 => 'thousand'] as $size => $word) {
        if ($n >= $size) {
            $rest = $n % $size;
            return number_words(intdiv($n, $size)) . ' ' . $word . ($rest ? ($rest < 100 ? ' and ' : ' ') . number_words($rest) : '');
        }
    }
    return (string) $n;
}

/** An amount of money in words for receipts: "Ghana Cedis Eight Hundred and Fifty, Twenty Pesewas Only". */
function money_words($amount): string
{
    $cents = (int) round(abs((float) $amount) * 100);
    $cedis = intdiv($cents, 100);
    $p = $cents % 100;
    $w = ($cedis === 1 ? 'Ghana Cedi ' : 'Ghana Cedis ') . number_words($cedis);
    if ($p) {
        $pw = number_words($p) . ($p === 1 ? ' pesewa' : ' pesewas');
        $w = $cedis ? $w . ', ' . $pw : $pw;
    }
    return (string) preg_replace('/\bAnd\b/', 'and', ucwords($w . ' only'));
}

/**
 * A page's switch (pill tabs): the main tabs, then a "More ▾" menu with the rest.
 * $tabs / $more = route => label; $active = the route to highlight; $query = extra query per route (optional).
 */
function seg_nav(array $tabs, array $more, string $active, string $aria = 'Pages'): string
{
    $inMore = isset($more[$active]);
    $h = '<nav class="seg" data-pagenav aria-label="' . e($aria) . '">';
    foreach ($tabs as $route => $label) {
        $h .= '<a href="' . e(url($route)) . '"' . ($active === $route ? ' class="active" aria-current="page"' : '') . '>' . e($label) . '</a>';
    }
    if ($more) {
        $h .= '<details class="menu-btn seg-more' . ($inMore ? ' active' : '') . '"><summary>' . e($inMore ? $more[$active] : 'More') . ' ▾</summary><div>';
        foreach ($more as $route => $label) {
            $h .= '<a href="' . e(url($route)) . '"' . ($active === $route ? ' aria-current="page"' : '') . '>' . e($label) . '</a>';
        }
        $h .= '</div></details>';
    }
    return $h . '</nav>';
}

/**
 * Downloads: start the file, add rows, finish. ?format=xlsx|docx|pdf gives Excel, Word or PDF (Export::send);
 * otherwise CSV (with the BOM Excel needs for GH₵ and accents). Text cells go through csv_safe(); numbers stay numbers.
 * Every module that uses these helpers offers all four formats.
 */
function csv_start(string $filename, ?array $head = null, ?string $title = null)
{
    $format = Export::format();
    if ($format !== 'csv') {
        return (object) ['filename' => $filename, 'head' => $head, 'rows' => [], 'format' => $format, 'title' => $title ?? Export::titleFrom($filename)];
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    if ($head !== null) {
        fputcsv($out, $head);
    }
    return $out;
}

/** $numbers = true also keeps number-like text ("-12.50") as a number (money reports). */
function csv_row($out, array $row, bool $numbers = false): void
{
    if ($out instanceof stdClass) {
        $out->rows[] = $row;
        return;
    }
    fputcsv($out, array_map(fn($v) => is_int($v) || is_float($v) || ($numbers && is_string($v) && is_numeric($v)) ? (string) $v : csv_safe($v), $row));
}

function csv_end($out): void
{
    if ($out instanceof stdClass) {
        Export::send($out->filename, $out->title, $out->head, $out->rows, $out->format);
        exit;
    }
    fclose($out);
    exit;
}

/** The Export ▾ menu: Excel, Word, PDF and CSV for one download address. */
function export_menu(string $href, string $label = 'Export', string $class = 'btn'): string
{
    $out = '<details class="menu-btn export-menu"><summary class="' . e($class) . '">' . e($label) . ' ▾</summary><div>';
    foreach (Export::FORMATS as $f => $name) {
        $out .= '<a href="' . e($href . (str_contains($href, '?') ? '&' : '?') . 'format=' . $f) . '" data-format="' . $f . '">' . e($name) . '</a>';
    }
    return $out . '</div></details>';
}

/** Export ▾ for an on-screen spreadsheet list (data-xgrid): exports exactly the rows and columns on screen. Outside the list box, pass the list's data-xgrid name as $for. */
function export_grid_menu(string $label = 'Export', string $for = ''): string
{
    $sv = fn(string $d, int $z = 18) => '<svg viewBox="0 0 24 24" width="' . $z . '" height="' . $z . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
    $file = fn(string $mark) => '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M6 2.5h8.5L19.5 7.5V20a1.5 1.5 0 0 1-1.5 1.5H6A1.5 1.5 0 0 1 4.5 20V4A1.5 1.5 0 0 1 6 2.5Z" fill="currentColor" opacity=".14"/><path d="M6 2.5h8.5L19.5 7.5V20a1.5 1.5 0 0 1-1.5 1.5H6A1.5 1.5 0 0 1 4.5 20V4A1.5 1.5 0 0 1 6 2.5Z" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M14.5 2.5V7.5h5" fill="none" stroke="currentColor" stroke-width="1.5"/>' . $mark . '</svg>';
    $items = [
        'xlsx' => ['Excel workbook', '.xlsx', 'xl', '<path d="M8.5 11.5l4 5M12.5 11.5l-4 5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>'],
        'docx' => ['Word document', '.docx', 'wd', '<path d="M8 11.5l1.3 5 1.7-4 1.7 4 1.3-5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>'],
        'pdf'  => ['PDF', '.pdf', 'pf', '<path d="M8 17v-5h1.6a1.4 1.4 0 0 1 0 2.8H8" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>'],
        'csv'  => ['CSV for other systems', '.csv', 'cv', '<path d="M8 12.5h8M8 15.5h8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>'],
    ];
    $out = '<details class="menu-btn export-menu"' . ($for !== '' ? ' data-xg-for="' . e($for) . '"' : '') . '><summary class="btn export-btn" aria-label="' . e($label) . ' this list">'
        . $sv('<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/>') . '<span>' . e($label) . '</span>'
        . '<span class="export-caret">' . $sv('<path d="m6 9 6 6 6-6"/>', 16) . '</span></summary>'
        . '<div class="export-pop" role="menu"><div class="export-head"><strong>Export list</strong><small data-export-meta>What is on screen</small></div>';
    foreach (Export::FORMATS as $f => $name) {
        [$t, $ext, $cls, $mark] = $items[$f] ?? [$name, '.' . $f, 'cv', ''];
        if ($f === 'csv') {
            $out .= '<hr class="export-sep">';
        }
        $out .= '<button type="button" class="menu-item export-item" role="menuitem" data-xg-export="' . $f . '">'
            . '<span class="ft ft-' . $cls . '">' . $file($mark) . '</span><span class="export-name">' . e($t) . '</span><span class="export-ext">' . e($ext) . '</span></button>';
    }
    return $out . '</div></details>';
}

/** The same four choices as plain links (inside an existing ⋯ or Tools menu). */
function export_links(string $href, string $prefix = 'Download'): string
{
    $out = '';
    foreach (Export::FORMATS as $f => $name) {
        $out .= '<a href="' . e($href . (str_contains($href, '?') ? '&' : '?') . 'format=' . $f) . '" data-format="' . $f . '">' . e($prefix . ' ' . $name) . '</a>';
    }
    return $out;
}

/** A whole CSV download in one call. */
function csv_download(string $filename, array $head, iterable $rows, bool $numbers = false): void
{
    $out = csv_start($filename, $head);
    foreach ($rows as $r) {
        csv_row($out, $r, $numbers);
    }
    csv_end($out);
}

/**
 * Least privilege on screen: may the signed-in person open this internal link / send this form?
 * $href may be a full app URL ("/NISDASSMS/fees?x=1") or a route ("fees"). Unknown or external links → true.
 */
/** False when the Super Admin has switched this module off (School setup → Modules on/off). */
function module_on(string $key): bool
{
    return ModuleSwitch::isOn($key);
}

function can_open(string $href, string $method = 'GET'): bool
{
    static $cache = [];
    $method = strtoupper($method);
    $base = rtrim(url(''), '/') . '/';
    $path = html_entity_decode($href, ENT_QUOTES);
    if (preg_match('#^[a-z]+://#i', $path) || str_starts_with($path, '#') || str_starts_with($path, 'mailto:') || str_starts_with($path, 'tel:')) {
        return true;
    }
    if (str_starts_with($path, '/')) {
        if (!str_starts_with($path, $base)) {
            return true; // assets or another site
        }
        $path = substr($path, strlen($base));
    }
    $path = trim(explode('#', explode('?', $path, 2)[0], 2)[0], '/');
    $key = $method . ' ' . $path;
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    if (str_starts_with($path, 'assets/') || Router::$current === null) {
        return $cache[$key] = true;
    }
    if (ModuleSwitch::blocked($path) !== null) {
        return $cache[$key] = false; // a switched-off module: no links or buttons to it anywhere
    }
    $perm = Router::$current->permissionFor($method, $path);
    if ($perm === false || $perm === null || $perm === 'webhook') {
        return $cache[$key] = true;
    }
    if (!Auth::check()) {
        return $cache[$key] = false;
    }
    if ($method === 'POST' && Auth::readOnly() && $path !== 'logout' && $path !== 'export/table' && !str_starts_with($path, 'account/')) {
        return $cache[$key] = false;
    }
    return $cache[$key] = ($perm === 'auth' || Auth::can($perm));
}

/**
 * Remove from a finished page everything the person may not use: buttons and menu links to pages
 * their role cannot open are dropped, other links become plain text, and forms they cannot send are removed.
 */
function strip_forbidden(string $html): string
{
    // Forms they cannot send (POST to a route their role lacks)
    $html = preg_replace_callback('#<form\b([^>]*)>.*?</form>#si', function ($m) {
        if (!preg_match('#\bmethod\s*=\s*"post"#i', $m[1]) || !preg_match('#\baction\s*=\s*"([^"]*)"#i', $m[1], $a)) {
            return $m[0];
        }
        if (can_open($a[1], 'POST')) {
            return $m[0];
        }
        // A form that also shows figures (a table or read-only boxes) stays visible without its buttons;
        // a form that is only an action (Approve, Save, Delete…) disappears.
        if (preg_match('#<table\b|\sdisabled\b|\sreadonly\b#i', $m[0])) {
            return preg_replace('#<button\b[^>]*>.*?</button>|<input\b[^>]*type\s*=\s*"submit"[^>]*>#si', '', $m[0]) ?? '';
        }
        return '';
    }, $html) ?? $html;
    // Links to pages they cannot open
    $out = '';
    $pos = 0;
    if (preg_match_all('#<a\b([^>]*)\bhref\s*=\s*"([^"]*)"([^>]*)>(.*?)</a>#si', $html, $all, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        foreach ($all as $m) {
            [$whole, $at] = $m[0];
            if (can_open($m[2][0])) {
                continue;
            }
            $attrs = $m[1][0] . ' ' . $m[3][0];
            $text = trim(strip_tags($m[4][0]));
            // The tag just before the link tells where it sits: in a sentence, a table cell or a list item it
            // stays as plain text; anywhere else (menus, button rows, tiles) it disappears.
            $before = substr($html, max(0, $at - 400), min(400, $at));
            $prevTag = preg_match_all('#<(/?)([a-z0-9]+)\b[^>]*>#i', $before, $t) ? strtolower(end($t[1]) . end($t[2])) : '';
            $inline = in_array($prevTag, ['td', 'th', 'p', 'li', 'strong', 'b', 'em', 'h1', 'h2', 'h3', 'h4', 'label', 'small', '/strong', '/b', '/em', 'br', '/span'], true);
            $drop = !$inline || $text === ''
                || preg_match('#class\s*=\s*"[^"]*\b(btn|tile|app-|eng-|mod-|xg-|menu|chip|bx-|dash-|card)#i', $attrs)
                || preg_match('#(→|›|»)\s*$#u', $text);
            $out .= substr($html, $pos, $at - $pos) . ($drop ? '' : '<span data-lp>' . $m[4][0] . '</span>');
            $pos = $at + strlen($whole);
        }
    }
    $html = $out . substr($html, $pos);
    return $html;
}

/** Where a parent or student lands: their own portal (staff go to the dashboard). */
function portal_home(): string
{
    return can('portal.parent') ? 'portal' : (Auth::role() === 'pupil' ? 'student' : (can('staff') ? 'dashboard' : (can('pickup.gate') ? 'gate' : 'learn')));
}

/**
 * Header cells for a spreadsheet list (data-xgrid): [key => [label, type ('text'|'num'|'date'), extra class]].
 * Pass $show (column keys to show) to leave out columns switched off in Administration → List columns.
 */
function list_th(array $cols, ?array $show = null): string
{
    $h = '';
    foreach ($cols as $k => $c) {
        if ($show !== null && !isset($show[$k])) {
            continue;
        }
        [$label, $type, $cls] = $c + [1 => 'text', 2 => ''];
        $h .= '<th scope="col" data-c="' . e($k) . '" data-type="' . e($type) . '"' . ($cls !== '' ? ' class="' . e($cls) . '"' : '') . '><button type="button" class="xg-sort" title="Sort">' . e($label) . '<span class="xg-arrow" aria-hidden="true"></span></button></th>';
    }
    return $h;
}

/** Status tabs inside a list card: [key => [label, url]]; the first $visible show as tabs, the rest under "More". */
function card_tabs(array $items, string $active, int $visible = 5, string $aria = 'Show'): string
{
    $main = array_slice($items, 0, $visible, true);
    $more = array_slice($items, $visible, null, true);
    $h = '<nav class="card-tabs" aria-label="' . e($aria) . '">';
    foreach ($main as $k => [$label, $href]) {
        $h .= '<a href="' . e($href) . '"' . ((string) $k === $active ? ' class="active" aria-current="page"' : '') . '>' . e($label) . '</a>';
    }
    if ($more) {
        $on = isset($more[$active]);
        $h .= '<details class="menu-btn card-more' . ($on ? ' active' : '') . '"><summary>' . e($on ? $more[$active][0] : 'More') . ' <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary><div>';
        foreach ($more as $k => [$label, $href]) {
            $h .= '<a href="' . e($href) . '"' . ((string) $k === $active ? ' aria-current="page"' : '') . '>' . e($label) . '</a>';
        }
        $h .= '</div></details>';
    }
    return $h . '</nav>';
}
