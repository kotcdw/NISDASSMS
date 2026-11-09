<div class="page-head"><div><h1>My security</h1><p class="muted"><?= e($u['name']) ?> · <?= e(Auth::roleLabel($u['role'])) ?></p></div>
    <div class="actions"><a class="btn" href="<?= e(url('account/password')) ?>">Change password</a></div></div>
<?php if ($newCodes): ?>
<div class="card" style="border:2px solid var(--warn, #c77700)">
    <h2>Your recovery codes</h2>
    <p>Each code works <strong>once</strong> if you lose your phone. Print this page or write them down now and keep them somewhere safe — they will not be shown again.</p>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:.4rem;font-family:ui-monospace,monospace;font-size:1.1rem">
        <?php foreach ($newCodes as $c): ?><span style="padding:.3rem .5rem;border:1px dashed #999;border-radius:6px;text-align:center"><?= e($c) ?></span><?php endforeach; ?>
    </div>
    <p style="margin-top:.8rem"><button class="btn" type="button" data-print>Print</button></p>
</div>
<?php endif; ?>
<div class="cols">
    <div class="card">
        <h2>2-step sign-in</h2>
        <?php if ($enabled): ?>
            <p><?= badge('On', 'good') ?> since <?= e(fmt_date(substr((string) $u['totp_enabled_at'], 0, 10))) ?> · <?= (int) $codesLeft ?> recovery code(s) left</p>
            <form method="post" action="<?= e(url('account/2fa/codes')) ?>" class="form" style="margin-bottom:1rem">
                <?= csrf_field() ?>
                <label>Make new recovery codes — current 6-digit code <input name="code" required inputmode="numeric" maxlength="7" style="max-width:180px"></label>
                <button class="btn" type="submit">New recovery codes</button>
            </form>
            <?php if (!$required): ?>
            <details><summary class="hint" style="cursor:pointer">Switch off 2-step sign-in</summary>
                <form method="post" action="<?= e(url('account/2fa/disable')) ?>" class="form" data-confirm="Switch off 2-step sign-in? Your account will be easier to break into.">
                    <?= csrf_field() ?>
                    <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
                    <label>6-digit code <input name="code" required inputmode="numeric" maxlength="11"></label>
                    <button class="btn btn-danger" type="submit">Switch off</button>
                </form>
            </details>
            <?php else: ?><p class="hint">Your role requires 2-step sign-in.</p><?php endif; ?>
        <?php else: ?>
            <p><?= badge('Off', 'warn') ?> Add a 6-digit code from your phone to your password. Strongly recommended for anyone who handles money or student records.</p>
            <a class="btn btn-primary" href="<?= e(url('account/2fa')) ?>">Set up 2-step sign-in</a>
        <?php endif; ?>
    </div>
    <div class="card" style="padding:0">
        <h2 style="padding:1rem 1rem 0">Where you are signed in</h2>
        <div class="table-wrap"><table>
            <tr><th>Device</th><th>IP</th><th>Last active</th><th></th></tr>
            <?php foreach ($sessions as $s): ?>
            <tr><td><?= e($s['device']) ?><?= $s['current'] ? ' ' . badge('This device', 'info') : '' ?><?= (int) $s['mfa'] ? ' ' . badge('2-step', 'good') : '' ?><br><small class="muted">Signed in <?= e(date('j M H:i', strtotime($s['created_at'] . ' UTC'))) ?></small></td>
                <td><small><?= e($s['ip']) ?></small></td><td><small><?= e(date('j M H:i', strtotime($s['last_seen_at'] . ' UTC'))) ?></small></td>
                <td><?php if (!$s['current']): ?><form method="post" action="<?= e(url('account/sessions/' . $s['id'] . '/revoke')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" type="submit">Sign out</button></form><?php endif; ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$sessions): ?><tr><td colspan="4" class="muted">No other sign-ins recorded.</td></tr><?php endif; ?>
        </table></div>
        <form method="post" action="<?= e(url('account/sessions/revoke-others')) ?>" style="padding:1rem" data-confirm="Sign out every other device?"><?= csrf_field() ?><button class="btn" type="submit">Sign out all other devices</button></form>
    </div>
</div>
