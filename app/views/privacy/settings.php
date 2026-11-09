<div class="page-head"><div><h1>Data protection</h1></div>
    <div class="actions"><a class="btn" href="<?= e(url('privacy')) ?>" target="_blank">View public notice</a></div></div>
<?php require dirname(__DIR__) . '/setup/_tabs.php'; ?>
<div class="cols">
<form method="post" action="<?= e(url('setup/privacy')) ?>" class="card form">
    <?= csrf_field() ?>
    <h2>Settings</h2>
    <div class="grid-2">
        <label>DPC registration no.<input name="dpc_registration_no" maxlength="60" value="<?= e(setting('dpc_registration_no')) ?>"></label>
        <label>Keep records (years after leaving)<input type="number" name="retention_years" min="1" max="50" required value="<?= (int) $years ?>"></label>
    </div>
    <label>Contact for data requests<input name="privacy_contact" maxlength="150" value="<?= e(setting('privacy_contact')) ?>"></label>
    <label>Privacy notice
        <textarea name="privacy_notice" rows="18" style="font-family:inherit"><?= e($custom !== '' ? $custom : $default) ?></textarea></label>
    <button class="btn btn-primary" type="submit">Save</button>
</form>
<div>
    <div class="card">
        <h2>Due for anonymising</h2>
        <?php if (!$due): ?>
            <p class="muted" style="margin:0">Nothing is due.</p>
        <?php elseif (!can('users.manage')): ?>
            <p><?= count($due) ?> former student(s) due.</p>
        <?php else: ?>
        <form method="post" action="<?= e(url('setup/privacy/anonymise')) ?>" class="form">
            <?= csrf_field() ?>
            <div class="table-wrap" style="max-height:360px;overflow:auto"><table>
                <tr><th><input type="checkbox" data-check-all="pupils[]" aria-label="Select all"></th><th>Student</th><th>Left</th><th>How</th></tr>
                <?php foreach ($due as $p): ?>
                <tr><td><input type="checkbox" name="pupils[]" value="<?= (int) $p['id'] ?>" aria-label="Select"></td><td><?= e($p['first_name'] . ' ' . $p['last_name']) ?> <small class="muted"><?= e($p['admission_no']) ?></small></td><td><?= e(fmt_date($p['left_on'])) ?></td><td><?= e(ucfirst($p['status'])) ?></td></tr>
                <?php endforeach; ?>
            </table></div>
            <label>Type ANONYMISE to confirm (this cannot be undone)<input name="confirm" autocomplete="off" required></label>
            <button class="btn btn-danger" type="submit">Anonymise selected</button>
        </form>
        <?php endif; ?>
    </div>

</div>
</div>
