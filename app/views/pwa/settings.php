<?php
$short = setting('app_short_name', 'NISDAS') ?: 'NISDAS';
$theme = app_theme_color();
$okCount = count(array_filter($checks, fn($c) => $c[1] === true));
$need = count(array_filter($checks, fn($c) => $c[1] !== null));
$device = function (string $ua): string {
    $os = preg_match('/iPhone|iPad/i', $ua) ? 'iPhone / iPad' : (preg_match('/Android/i', $ua) ? 'Android' : (preg_match('/Windows/i', $ua) ? 'Windows' : (preg_match('/Mac OS/i', $ua) ? 'Mac' : 'Other')));
    $br = preg_match('/Edg\//', $ua) ? 'Edge' : (preg_match('/SamsungBrowser/', $ua) ? 'Samsung Internet' : (preg_match('/Chrome\//', $ua) ? 'Chrome' : (preg_match('/Firefox\//', $ua) ? 'Firefox' : (preg_match('/Safari\//', $ua) ? 'Safari' : 'Browser'))));
    return $os . ' · ' . $br;
};
?>
<div class="page-head"><div><h1>Phone app</h1></div>
    <div class="actions"><form method="post" action="<?= e(url('push/test')) ?>" class="inline"><?= csrf_field() ?><button class="btn" type="submit"<?= $mine ? '' : ' disabled title="Turn on notifications on your phone first (bell → Turn on phone notifications)"' ?>>Send a test to my phone<?= $mine ? ' (' . $mine . ')' : '' ?></button></form></div>
</div>
<?php $tab = in_array($_GET['tab'] ?? '', ['settings', 'devices'], true) ? $_GET['tab'] : 'install'; ?>
<nav class="seg" aria-label="Phone app">
    <a href="<?= e(url('setup/app')) ?>"<?= $tab === 'install' ? ' class="active"' : '' ?>>Install &amp; invite</a>
    <a href="<?= e(url('setup/app', ['tab' => 'settings'])) ?>"<?= $tab === 'settings' ? ' class="active"' : '' ?>>App settings</a>
    <a href="<?= e(url('setup/app', ['tab' => 'devices'])) ?>"<?= $tab === 'devices' ? ' class="active"' : '' ?>>Devices (<?= count($devices) ?>)</a>
</nav>
<?php if ($tab === 'install'): ?>
<div class="app-hero">
    <div class="card app-preview-card">
        <div class="app-share">
            <h2>Open on a phone</h2>
            <div class="app-qr" data-qr="<?= e($link) ?>" data-qr-label="QR code to open the school system on a phone"></div>
            <p class="app-link"><code><?= e($link) ?></code></p>
                    </div>
    </div>
    <div class="card">
        <h2>Ready to install? <span class="app-score"><?= $okCount ?> of <?= $need ?></span></h2>
        <ul class="app-checks">
            <?php foreach ($checks as [$label, $ok, $detail, $fix]): ?>
            <li class="<?= $ok === true ? 'ok' : ($ok === null ? 'opt' : 'no') ?>">
                <span class="app-check-ico" aria-hidden="true"><?= $ok === true ? '✓' : ($ok === null ? '–' : '!') ?></span>
                <div><strong><?= e($label) ?></strong><?php if ($ok === false): ?><small class="fix"><?= e($fix) ?></small><?php endif; ?></div>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>



<?php if (can('messages.send')): ?>
<div class="card">
    <h2>Invite by SMS</h2>
    <div class="actions" style="justify-content:flex-start;flex-wrap:wrap">
        <form method="post" action="<?= e(url('setup/app/invite')) ?>" class="inline" data-confirm="Send the app invitation by SMS to <?= (int) $reach['parents'] ?> parent phone number(s)?" data-confirm-ok="Send invitation"><?= csrf_field() ?><input type="hidden" name="audience" value="parents"><button class="btn btn-primary" type="submit"<?= $reach['parents'] ? '' : ' disabled' ?>>SMS all parents (<?= (int) $reach['parents'] ?>)</button></form>
        <form method="post" action="<?= e(url('setup/app/invite')) ?>" class="inline" data-confirm="Send the app invitation by SMS to <?= (int) $reach['staff'] ?> staff phone number(s)?" data-confirm-ok="Send invitation"><?= csrf_field() ?><input type="hidden" name="audience" value="staff"><button class="btn" type="submit"<?= $reach['staff'] ? '' : ' disabled' ?>>SMS all staff (<?= (int) $reach['staff'] ?>)</button></form>
    </div>
    </div>
<?php endif; ?>

<?php elseif ($tab === 'settings'): ?>
<div class="app-settings-wrap">
<form method="post" action="<?= e(url('setup/app')) ?>" class="card form"><?= csrf_field() ?>
    <h2>App settings</h2>
    <label>App name<input name="app_short_name" maxlength="12" value="<?= e($short) ?>" data-app-name-input></label>
    <fieldset class="app-colors"><legend>Top bar colour</legend>
        <?php foreach (PwaController::THEMES as $hex => $nm): ?><label title="<?= e($nm) ?>"><input type="radio" name="app_theme_color" value="<?= e($hex) ?>"<?= checked($theme === $hex) ?> data-app-color><span style="background:<?= e($hex) ?>"></span><?= e($nm) ?></label><?php endforeach; ?>
    </fieldset>
    <details<?= setting('play_package') ? ' open' : '' ?>><summary>Google Play listing (optional)</summary>
        <label>Package name<input name="play_package" value="<?= e(setting('play_package')) ?>" placeholder="academy.nisdasms.app"></label>
        <label>SHA-256 fingerprint<textarea name="play_sha256" rows="3" placeholder="AB:CD:…  (separate several with commas)"><?= e(setting('play_sha256')) ?></textarea></label>
    </details>
    <button class="btn btn-primary" type="submit">Save</button>
</form>
</div>

<?php else: ?>
<div class="card" style="padding:0">
    <h2 style="padding:1rem 1.2rem 0">Devices with notifications</h2>
    <div class="table-wrap"><table>
        <tr><th>Person</th><th>Role</th><th>Device</th><th>Turned on</th><th>Last notification</th><th></th></tr>
        <?php foreach ($devices as $d): ?><tr>
            <td><?= e($d['name']) ?></td><td><?= e(Auth::roleLabel($d['role'])) ?></td><td><?= e($device((string) $d['user_agent'])) ?></td>
            <td><?= e(fmt_date($d['created_at'], 'j M Y')) ?></td><td><?= $d['last_used_at'] ? e(fmt_date($d['last_used_at'], 'j M H:i')) : '—' ?></td>
            <td><form method="post" action="<?= e(url('setup/app/devices/' . $d['id'] . '/remove')) ?>" class="inline" data-confirm="Stop notifications to this device of <?= e($d['name']) ?>? Use this when a phone is lost or changed." data-confirm-ok="Remove device" data-confirm-tone="danger"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" type="submit">Remove</button></form></td>
        </tr><?php endforeach; ?>
        <?php if (!$devices): ?><tr><td colspan="6" class="muted">No device has turned on notifications yet.</td></tr><?php endif; ?>
    </table></div>
</div>
<?php endif; ?>
<script src="<?= e(asset('js/qrcode.js')) ?>"></script>
<script src="<?= e(asset('js/totp-qr.js')) ?>"></script>
