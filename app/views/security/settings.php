<?php $n = fn($k, $d) => (int) setting($k, (string) $d); $on = fn($k, $d = '0') => setting($k, $d) === '1'; ?>
<div class="page-head"><div><h1>Sign-in rules</h1></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<form method="post" action="<?= e(url('security/settings')) ?>" class="form form-center fs-form">
    <?= csrf_field() ?>
    <div class="card">
        <h2>2-step sign-in</h2>
        <label class="fs-check"><input type="checkbox" name="mfa_enforced" value="1"<?= checked($on('mfa_enforced', '1')) ?>> On <?= $on('mfa_enforced', '1') ? '' : badge('Off for testing', 'warn') ?></label>
    </div>
    <div class="card">
        <h2>Passwords</h2>
        <div class="grid-2">
            <label>Staff minimum length<input type="number" name="pw_min_length" min="8" max="64" value="<?= $n('pw_min_length', 10) ?>"></label>
            <label>Parent / student minimum length<input type="number" name="pw_portal_min_length" min="6" max="64" value="<?= $n('pw_portal_min_length', 8) ?>"></label>
            <label>Remember last (passwords)<input type="number" name="pw_history" min="0" max="24" value="<?= $n('pw_history', 5) ?>"></label>
            <label>Staff change every (days)<input type="number" name="pw_expiry_days" min="0" max="730" value="<?= $n('pw_expiry_days', 0) ?>"></label>
        </div>
        <label class="fs-check"><input type="checkbox" name="pw_require_mix" value="1"<?= checked($on('pw_require_mix', '1')) ?>> Letters + number or symbol</label>
    </div>
    <div class="card">
        <h2>Lock-out &amp; sessions</h2>
        <div class="grid-3">
            <label>Wrong tries<input type="number" name="login_max_tries" min="3" max="20" value="<?= $n('login_max_tries', (int) config('login_max_tries', 5)) ?>"></label>
            <label>Lock-out (minutes)<input type="number" name="login_lock_mins" min="5" max="1440" value="<?= $n('login_lock_mins', (int) config('login_lock_mins', 15)) ?>"></label>
            <label>Session limit (hours)<input type="number" name="session_absolute_hours" min="1" max="24" value="<?= $n('session_absolute_hours', 12) ?>"></label>
            <label>Re-confirm password (minutes)<input type="number" name="step_up_minutes" min="1" max="60" value="<?= $n('step_up_minutes', 10) ?>"></label>
            <label>Switch off unused after (days)<input type="number" name="dormant_days" min="0" max="730" value="<?= $n('dormant_days', 0) ?>"></label>
        </div>
        <label class="fs-check"><input type="checkbox" name="single_session" value="1"<?= checked($on('single_session')) ?>> One device at a time (staff)</label>
        <label class="fs-check"><input type="checkbox" name="login_alerts" value="1"<?= checked($on('login_alerts', '1')) ?>> New-device sign-in alerts</label>
        <label class="fs-check"><input type="checkbox" name="login_alert_sms" value="1"<?= checked($on('login_alert_sms')) ?>> SMS the Super Admin too</label>
    </div>
    <div class="fs-actions"><button class="btn btn-primary" type="submit">Save</button></div>
</form>
