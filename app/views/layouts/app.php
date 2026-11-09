<?php
$user = auth();
$term = current_term();
$route = current_route();
$isStaff = can('staff');
// Administration links (Super Admin and Administrator) — in the side menu and the account menu.
$adminItems = Engine::admin();
$appSettings = can('app.configure') ? [
    ['School & academic setup', 'setup'],
    ['Class subjects', 'setup/subjects'],
    ['Setup checklist', 'setup/wizard'],
    ['Modules & features', 'setup/modules'],
    ['Data protection', 'setup/privacy'],
    ['Data requests', 'setup/privacy/requests'],
    ['List columns', 'setup/columns'],
    ['Security settings', 'security/settings'],
    ['Messaging settings', 'messages/settings'],
    ['Message templates', 'messages/templates'],
    ['Exam grading setup', 'exams/setup'],
    ['Fee settings', 'fees/settings'],
    ['Fee schedule', 'fees/schedule'],
    ['Discounts & scholarships', 'fees/discounts'],
    ['Finance accounts & categories', 'finance/setup'],
    ['Phone app settings', 'setup/app'],
] : [];
$appSettings[] = can('app.configure') && can('finance.view') ? ['Withholding tax rates', 'suppliers/wht#wht-rates'] : null;
$appSettings = array_values(array_filter($appSettings));
$settingsRoutes = array_values(array_filter(array_map(
    static fn($item) => str_contains($item[1], '#') ? null : $item[1],
    $appSettings
)));
if (can('app.configure') && can('payroll.view')) {
    $appSettings[] = ['Tax & SSNIT rates', 'payroll#rates-box'];
}
if (can('app.configure') && can('staff_att.view')) {
    $appSettings[] = ['Staff attendance settings', 'attendance/staff#late-time'];
}
if ($settingsRoutes) {
    $adminItems = array_values(array_filter($adminItems, static fn($item) => !in_array($item[2], $settingsRoutes, true)));
}
require __DIR__ . '/_areanav.php'; // the module of this page ($anArea, $anAreas, $anActive, $anTabs)
// The page's own sub-pages (marked data-pagenav) are taken out of the page and drawn as tabs above it;
// the module's + New menu (data-sidepanel) sits at the right of those tabs.
$pageNav = [];
$pageNavLabel = '';
if (preg_match('#<nav class="seg[^"]*" data-pagenav([^>]*)>(.*?)</nav>#s', $content, $pm)) {
    $content = str_replace($pm[0], '', $content);
    if (preg_match('#aria-label="([^"]*)"#', $pm[1], $al)) {
        $pageNavLabel = html_entity_decode($al[1], ENT_QUOTES, 'UTF-8');
    }
    // Links inside a "More" dropdown (seg_nav's details.seg-more) stay in a More dropdown here too
    $navMain = $pm[2];
    $navMore = '';
    if (preg_match('#<details[^>]*seg-more.*?</details>#s', $navMain, $mm)) {
        $navMore = $mm[0];
        $navMain = str_replace($mm[0], '', $navMain);
    }
    foreach ([[$navMain, false], [$navMore, true]] as [$chunk, $isMore]) {
        preg_match_all('#<a\b([^>]*)>(.*?)</a>#s', $chunk, $links, PREG_SET_ORDER);
        foreach ($links as [, $attrs, $inner]) {
            if (!preg_match('#href="([^"]*)"#', $attrs, $hm)) {
                continue;
            }
            $pageNav[] = [html_entity_decode($hm[1], ENT_QUOTES, 'UTF-8'), trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($inner), ENT_QUOTES, 'UTF-8'))),
                (bool) preg_match('#class="[^"]*\bactive\b|aria-current=#', $attrs), $isMore];
        }
    }
}
$panelExtra = '';
if (preg_match_all('#<details[^>]*\bdata-sidepanel\b.*?</details>#s', $content, $sp)) {
    foreach ($sp[0] as $blk) {
        $content = str_replace($blk, '', $content);
        $panelExtra .= $blk;
    }
}
// Finance destinations, including the Payroll links grouped here, share one navigation.
$financeMenu = $isStaff && ($anArea === 'Finance' || FinanceMenu::groupOf($route) !== null) ? FinanceMenu::visible() : [];
// Left app panel (same style as the Finance panel): the app's modules as collapsible groups with their pages (8 Nov 2026)
$appName = $isStaff ? AppMenu::forRoute($route) : null;
$appPanel = $appName !== null ? AppMenu::visible($appName) : [];
if (count(AppMenu::paths($appPanel)) < 2) {
    [$appName, $appPanel] = [null, []];
}
if ($appPanel) {
    // Page tabs that the panel already lists are not repeated above the page
    $apPaths = AppMenu::paths($appPanel);
    $apBase = (string) parse_url(url(''), PHP_URL_PATH);
    $apCovered = true;
    foreach ($pageNav as [$href]) {
        $hp = (string) parse_url($href, PHP_URL_PATH);
        $hr = trim(str_starts_with($hp, $apBase) ? substr($hp, strlen($apBase)) : $hp, '/');
        if (!in_array($hr, $apPaths, true)) {
            $apCovered = false;
            break;
        }
    }
    if ($apCovered) {
        $pageNav = [];
    }
    [$apModule, $apLink] = AppMenu::here($appPanel, $route);
}
$adminNow = '';
foreach ($adminItems as [, , $adminR]) { // the Administration link that is "here" (longest matching route)
    if (AppMenu::matchLen($adminR, $route) > strlen($adminNow)) {
        $adminNow = $adminR;
    }
}

// Side menu: this person's apps grouped as on NISDAS APPS; the item with the longest matching route is active.
$navItems = Menu::visible();
if ($settingsRoutes) {
    $navItems = array_values(array_filter($navItems, static fn($item) => !in_array($item[0], $settingsRoutes, true)));
}
$navActive = null;
$navBest = -1;
foreach ($navItems as $i => [$r]) {
    if (($route === $r || str_starts_with($route . '/', $r . '/')) && strlen($r) > $navBest) {
        [$navActive, $navBest] = [$i, strlen($r)];
    }
}
// Pages not in the menu (e.g. Import, Promotion) light up the menu item of their module section
if ($navActive === null && $anArea !== null && $anActive !== null && isset($anAreas[$anArea][$anActive])) {
    foreach ($navItems as $i => [$r]) {
        if ($r === $anAreas[$anArea][$anActive][0]) {
            $navActive = $i;
            break;
        }
    }
}
if (str_starts_with($route . '/', 'dashboard/') || $route === 'modules' || str_starts_with($route, 'modules/')) {
    $navActive = null;
}
$activeFinanceGroup = $financeMenu ? (FinanceMenu::groupOf($route) ?? (str_starts_with($route, 'fees/') ? 'Fees' : null)) : null;
$activeGroup = $navActive !== null ? $navItems[$navActive][2] : '';
$crumb = $navActive !== null ? ($activeGroup !== '' ? $activeGroup : '') : '';
if ($crumb === '' && $anArea !== null && !in_array($anArea, ['Parent portal', 'My learning'], true)) {
    $crumb = $anArea; // pages reached from area tabs (Lesson notes, Ledger, Petty cash…) still show their area
}
if ($appPanel && $appName !== null) {
    $crumb = $appName; // the label above the page title names the same app as the left panel
}
$pageTitle = (string) ($title ?? '');

$unread = (int) db()->value('SELECT COUNT(*) FROM inapp_notifications WHERE user_id = ? AND read_at IS NULL', [$user['id']]);
$myIn = $isStaff ? db()->one('SELECT time_in, time_out FROM staff_attendance WHERE user_id = ? AND date = CURDATE()', [Auth::id()]) : null;
$attn = $isStaff ? Attention::items() : [];
$initial = mb_strtoupper(mb_substr(trim(preg_replace('/^(Mr|Mrs|Ms|Miss|Dr|Rev)\.?\s+/i', '', $user['name'])), 0, 1));
$home = url($isStaff ? 'dashboard' : portal_home());
$staffSign = function (string $cls) use ($myIn): void { ?>
    <form method="post" action="<?= e(url('attendance/staff/sign')) ?>" class="staff-sign <?= $cls ?>"><?= csrf_field() ?>
        <?php if (!$myIn): ?><span class="pf-status">Not signed in yet</span><button class="btn btn-good btn-sm" type="submit">Staff sign-in</button>
        <?php elseif (!$myIn['time_out']): ?><span class="pf-status ok">● In since <?= e(substr((string) $myIn['time_in'], 0, 5)) ?></span><button class="btn btn-sm" type="submit">Sign out of work</button>
        <?php else: ?><span class="pf-status ok">✓ <?= e(substr((string) $myIn['time_in'], 0, 5)) ?>–<?= e(substr((string) $myIn['time_out'], 0, 5)) ?> · signed in &amp; out</span><?php endif; ?>
    </form>
<?php };
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="quick-add-url" content="<?= e(url('quick-add')) ?>">
<meta name="export-url" content="<?= e(url('export/table')) ?>">
<title><?= e($pageTitle !== '' ? $pageTitle . ' · ' : '') ?><?= e(school_name()) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<script src="<?= e(asset('js/shell.js')) ?>"></script>
<?= favicon_tags() ?>
</head>
<body class="app-body<?= !empty($bodyClass) ? ' ' . e($bodyClass) : '' ?>">
<a class="skip-link" href="#main">Skip to the page</a>
<?php if (is_demo()): ?><div class="demo-banner">PRACTICE COPY — made-up records for training. Nothing here affects the real school records, and no SMS are sent. <a href="<?= e(rtrim(dirname(app_base()), '/\\') . '/') ?>">Go to the real system</a></div><?php endif; ?>
<div class="shell">
    <div class="app-main">
        <header class="topbar">
            <a class="tb-brand" href="<?= e($home) ?>" title="<?= e(school_name()) ?> — home"><img src="<?= e(app_logo_url()) ?>" alt="<?= e(school_name()) ?>"></a>
            <div class="tb-title">
                <?php if ($crumb !== ''): ?><span class="tb-crumb"><?= e($crumb) ?></span><?php endif; ?>
                <span class="tb-page"><?= e($pageTitle !== '' ? $pageTitle : school_name()) ?></span>
            </div>
            <div class="tb-left">
                <div class="term-chip" title="Current term">
                    <?php if ($term): ?><?= e($term['year_name']) ?> · <?= e($term['name']) ?><?php else: ?><span>No current term set</span><?php endif; ?>
                </div>
                <?php if ($isStaff): ?><?php $staffSign('tb-sign'); ?><?php endif; ?>
            </div>
            <div class="tb-tools">
                <?php if ($isStaff): ?>
                    <a class="tb-icon tb-apps<?= $route === 'modules' ? ' is-here' : '' ?>" href="<?= e(url('modules')) ?>" title="NISDAS APPS" aria-label="NISDAS APPS"><?= Icons::svg('apps') ?></a>
                    <details class="menu-btn attn-menu tb-attn">
                        <summary class="tb-icon" aria-label="Needs your attention: <?= count($attn) ?>" title="Needs your attention"><?= Icons::svg('alert') ?><?php if ($attn): ?><span class="tb-count warn"><?= count($attn) > 99 ? '99+' : count($attn) ?></span><?php endif; ?></summary>
                        <div class="tb-pop">
                            <div class="pf-label">Needs your attention <?php if ($attn): ?><span class="attn-count"><?= count($attn) ?></span><?php endif; ?></div>
                            <div class="attn-list">
                                <?php foreach ($attn as [$n, $label, $aRoute, $tone]): ?><a class="<?= e($tone) ?>" href="<?= e(url($aRoute)) ?>"><span class="n"><?= (int) $n ?></span><?= e($label) ?></a><?php endforeach; ?>
                                <?php if (!$attn): ?><p>✓ Nothing waiting for you.</p><?php endif; ?>
                            </div>
                        </div>
                    </details>
                <?php endif; ?>
                <?php if (!$isStaff && $navItems): ?>
                    <details class="menu-btn tb-launch">
                        <summary class="tb-icon tb-apps" title="My apps" aria-label="My apps"><?= Icons::svg('apps') ?></summary>
                        <div class="tb-pop tb-launch-pop">
                            <?php foreach ($navItems as $i => [$r, $label, , $icon]): ?><a href="<?= e(url($r)) ?>"<?= $i === $navActive ? ' aria-current="page"' : '' ?>><?= Icons::svg($icon) ?><span><?= e($label) ?></span></a><?php endforeach; ?>
                        </div>
                    </details>
                <?php endif; ?>
                <a class="tb-icon bell" href="<?= e(url('notifications')) ?>" title="Notifications" aria-label="Notifications<?= $unread ? ': ' . $unread . ' unread' : '' ?>"><?= Icons::svg('bell') ?><?php if ($unread): ?><span class="tb-count"><?= $unread > 99 ? '99+' : $unread ?></span><?php endif; ?></a>
                <details class="profile-menu">
                    <summary class="profile-btn" aria-label="Your account menu">
                        <span class="pf-avatar"><?= e($initial) ?></span>
                        <span class="pf-who"><strong><?= e($user['name']) ?></strong><small><?= e(Auth::roleLabel($user['role'])) ?></small></span>
                        <?= Icons::svg('down', 16, 'ico pf-caret') ?>
                    </summary>
                    <div class="profile-panel">
                        <div class="pf-head">
                            <span class="pf-avatar lg"><?= e($initial) ?></span>
                            <div><strong><?= e($user['name']) ?></strong><small><?= e(Auth::roleLabel($user['role'])) ?><?= !empty($user['email']) ? ' · ' . e($user['email']) : '' ?></small></div>
                        </div>
                        <?php if ($term): ?><div class="pf-section pf-term"><span class="pf-label">Current term</span><?= e($term['year_name']) ?> · <?= e($term['name']) ?></div><?php endif; ?>
                        <?php if ($isStaff): ?>
                        <div class="pf-section pf-today-wrap">
                            <div class="pf-label">Today</div>
                            <?php $staffSign('pf-today'); ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($adminItems): ?>
                        <div class="pf-section pf-links pf-admin">
                            <div class="pf-label">Administration</div>
                            <?php foreach ($adminItems as [$label, $href, $r]): ?><a href="<?= e($href) ?>"<?= $r === $adminNow ? ' class="active" aria-current="page"' : '' ?>><span class="pf-ico">⚙</span><?= e($label) ?></a><?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($appSettings): ?>
                        <div class="pf-section pf-links pf-app-settings">
                            <div class="pf-label">App settings</div>
                            <?php foreach ($appSettings as [$label, $r]): $path = strtok($r, '#'); $active = $path === $route || ($path !== 'setup' && str_starts_with($route, $path . '/')); ?><a href="<?= e(url($r)) ?>"<?= $active ? ' class="active" aria-current="page"' : '' ?>><span class="pf-ico">⚙</span><?= e($label) ?></a><?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <div class="pf-section pf-links">
                            <a href="<?= e(url('notifications')) ?>" class="bell"><span class="pf-ico">🔔</span>Notifications<?php if ($unread): ?><span class="bell-count"><?= $unread > 99 ? '99+' : $unread ?></span><?php endif; ?></a>
                            <?php if (can('payslips.own')): ?><a href="<?= e(url('my/payslips')) ?>"><span class="pf-ico">₵</span>My payslips</a><?php endif; ?>
                            <?php if ($isStaff): ?><a href="<?= e(url('timesheets/my')) ?>"><span class="pf-ico">⏱</span>My timesheet</a><?php if (!can('finance.view') && Petty::mine()): ?><a href="<?= e(url('petty-cash')) ?>"><span class="pf-ico">₵</span>My petty cash</a><?php endif; ?><a href="<?= e(url('teachers/me')) ?>"><span class="pf-ico">☺</span>My profile &amp; appraisal</a><?php endif; ?>
                            <?php if (can('documents.view')): ?><a href="<?= e(url('documents/my')) ?>"><span class="pf-ico">▤</span>My files</a><?php endif; ?>
                            <a href="<?= e(url('account/security')) ?>"><span class="pf-ico">🔒</span>My security</a>
                            <a href="<?= e(url('help')) ?>"><span class="pf-ico">?</span>Help &amp; training guide</a>
                        </div>
                        <form method="post" action="<?= e(url('logout')) ?>" class="pf-signout">
                            <?= csrf_field() ?>
                            <button type="submit"><span class="pf-ico">⎋</span>Sign out</button>
                        </form>
                    </div>
                </details>
            </div>
        </header>

        <?php ob_start(); ?>
            <?php if ($pageNav): ?>
            <nav class="tabs area-tabs<?= in_array($route, ['payroll', 'payroll/staff', 'payroll/annual'], true) ? ' payroll-page-tabs' : '' ?>" aria-label="<?= e($pageNavLabel ?: 'Pages') ?>">
                <?php $pnMore = array_filter($pageNav, fn($n) => !empty($n[3])); ?>
                <?php foreach ($pageNav as [$href, $label, $on, $isMore]): if ($isMore) continue; ?><a href="<?= e($href) ?>"<?= $on ? ' class="active" aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
                <?php if ($pnMore): $pnOn = array_values(array_filter($pnMore, fn($n) => $n[2])); ?>
                <details class="menu-btn tab-more<?= $pnOn ? ' active' : '' ?>"><summary><?= e($pnOn ? $pnOn[0][1] : 'More') ?><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary><div>
                    <?php foreach ($pnMore as [$href, $label, $on]): ?><a href="<?= e($href) ?>"<?= $on ? ' aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
                </div></details>
                <?php endif; ?>
            </nav>
            <?php endif; ?>
        <?php
        $navHtml = trim(ob_get_clean());
        // Keep page-level Finance tabs beside their page title when no grouped links are available.
        $navInHead = false;
        if ($navHtml !== '' && !$financeMenu && $anArea === 'Finance' && preg_match('#<div class="page-head[^"]*">\s*<div[^>]*>.*?</div>#s', $content)) {
            $content = preg_replace('#(<div class="page-head[^"]*">\s*<div[^>]*>.*?</div>)#s', '$1<div class="ph-nav">' . str_replace(['\\', '$'], ['\\\\', '\\$'], $navHtml) . '</div>'
                . ($panelExtra !== '' ? '<div class="ph-extra">' . str_replace(['\\', '$'], ['\\\\', '\\$'], $panelExtra) . '</div>' : ''), $content, 1);
            $navInHead = true;
            $panelExtra = ''; // the module's + New menu moved into the page header too
        }
        ?>
        <?php
        // App panel pages: the page title row stays full width above the panel and the page; the app's + New menu sits in it
        $apHead = '';
        if ($appPanel) {
            if (($hs = strpos($content, '<div class="page-head')) !== false && $hs < 4000
                && preg_match_all('#<div\\b#', substr($content, 0, $hs)) === preg_match_all('#</div\\b#', substr($content, 0, $hs))) { // only a top-level title row
                $depth = 0;
                $pos = $hs;
                while (preg_match('#<(/?)div\b#', $content, $tm, PREG_OFFSET_CAPTURE, $pos)) {
                    $depth += $tm[1][0] === '/' ? -1 : 1;
                    $pos = $tm[0][1] + 4;
                    if ($depth === 0) {
                        $end = strpos($content, '>', $tm[0][1]) + 1;
                        $apHead = substr($content, 0, $end);
                        $content = substr($content, $end);
                        break;
                    }
                }
            }
            if ($apHead !== '' && $panelExtra !== '') {
                $cut = strrpos($apHead, '</div>');
                $apHead = substr($apHead, 0, $cut) . '<div class="ph-extra">' . $panelExtra . '</div>' . substr($apHead, $cut);
                $panelExtra = '';
            }
        }
        ?>
        <?php if (($navHtml !== '' && !$navInHead) || $panelExtra !== ''): ?>
        <div class="tabbar">
            <?= $navInHead ? '' : $navHtml ?>
            <?php if ($panelExtra !== ''): ?><div class="tab-extra"><?= $panelExtra ?></div><?php endif; ?>
        </div>
        <?php endif; ?>

        <main class="content app-page" id="main" tabindex="-1">
            <?php if (can('users.manage') && ($pending = Migrator::pending(db()->pdo()))): ?>
                <div class="alert alert-warning">
                    <form method="post" action="<?= e(url('setup/migrate')) ?>" class="inline">
                        <?= csrf_field() ?>The automatic database update did not finish (<?= e(implode(', ', $pending)) ?>).
                        <button class="btn btn-sm btn-primary" type="submit">Update database now</button>
                    </form>
                </div>
            <?php endif; ?>
            <?php foreach (take_flashes() as $f): ?>
                <div class="alert alert-<?= e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($f['message']) ?></div>
            <?php endforeach; ?>
            <?php if ($appPanel): ?>
            <?= $apHead ?>
            <input type="checkbox" id="app-panel-check" class="app-panel-check">
            <label for="app-panel-check" class="btn app-panel-open"><?= Icons::svg('apps') ?><?= e($appName) ?> menu</label>
            <div class="finance-module-layout app-panel-layout">
                <aside class="card finance-overview-sidebar app-panel" aria-label="<?= e($appName) ?> pages">
                    <?php foreach ($appPanel as $apGroup => $apSections): ?>
                    <details class="finance-overview-group"<?= $apGroup === $apModule || count($appPanel) === 1 ? ' open' : '' ?>>
                        <summary><?= e($apGroup) ?> <span>▾</span></summary>
                        <div class="finance-overview-links">
                            <?php foreach ($apSections as $apSection => $apItems): ?>
                                <?php if ($apSection !== ''): ?><h3><?= e($apSection) ?></h3><?php endif; ?>
                                <?php foreach ($apItems as [$apRoute, $apLabel, $apDesc]): ?>
                                <a href="<?= e(url($apRoute)) ?>"<?= $apDesc !== '' ? ' title="' . e($apDesc) . '"' : '' ?><?= $apRoute === $apLink && $apGroup === $apModule ? ' aria-current="page"' : '' ?>>
                                    <strong><?= e($apLabel) ?></strong>
                                </a>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </div>
                    </details>
                    <?php endforeach; ?>
                </aside>
                <div class="app-panel-content"><?= $content ?></div>
            </div>
            <?php else: ?>
            <?= $content ?>
            <?php endif; ?>
        </main>
    </div>
</div>
<?php require __DIR__ . '/_quick_add.php'; ?>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
