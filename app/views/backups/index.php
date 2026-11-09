<?php if ($copyOk === false): ?><div class="alert alert-error">Copy folder not reachable.</div><?php endif; ?>
<div class="xgrid-box" data-xgrid="backups" data-xgrid-file="backups">
<div class="page-head">
    <div><h1>Backups</h1></div>
    <div class="actions">
        <button type="button" class="btn btn-ghost" data-help="backup-settings" aria-expanded="false">Settings</button>
        <?= export_grid_menu() ?>
        <form method="post" action="<?= e(url('backups')) ?>"><?= csrf_field() ?><button class="btn btn-primary" type="submit">Make a backup now</button></form>
    </div>
</div>
<form id="backup-settings" method="post" action="<?= e(url('backups/settings')) ?>" class="card form list-form" hidden>
    <?= csrf_field() ?>
    <h2>Settings</h2>
    <label style="font-weight:400"><input type="checkbox" name="backup_auto" value="1"<?= checked(setting('backup_auto', '1') === '1') ?>> Daily automatic backup</label>
    <div class="grid-3">
        <label>After (hour, 0–23)<input type="number" name="backup_hour" min="0" max="23" value="<?= (int) setting('backup_hour', '18') ?>"></label>
        <label>Keep the last<input type="number" name="backup_keep" min="3" max="90" value="<?= (int) setting('backup_keep', '14') ?>"></label>
        <label>Copy to folder<input name="backup_copy_dir" value="<?= e(setting('backup_copy_dir')) ?>" placeholder="E:\NISDAS-Backups"></label>
    </div>
    <div class="gate-actions"><button class="btn btn-primary" type="submit">Save</button><button type="button" class="btn btn-ghost" data-help="backup-settings">Cancel</button></div>
</form>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['file' => ['Backup'], 'made' => ['Made', 'date'], 'how' => ['Made by'], 'tables' => ['Tables', 'num', 'num'], 'size' => ['Size', 'num', 'num'], 'copied' => ['Copied to']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($files as $f): ?>
    <tr>
        <td data-c="file"><code><?= e($f['file_name']) ?></code></td>
        <td data-c="made" data-s="<?= e($f['created_at']) ?>"><?= e(date('D d M Y H:i', strtotime($f['created_at'] . ' UTC'))) ?></td>
        <td data-c="how"><?= $f['kind'] === 'auto' ? 'Automatic' : e($f['by_name'] ?? 'Command line') ?></td>
        <td data-c="tables" class="num"><?= (int) $f['tables_count'] ?></td>
        <td data-c="size" class="num" data-s="<?= (int) $f['size_bytes'] ?>"><?= e(Backup::size((int) $f['size_bytes'])) ?></td>
        <td data-c="copied"><?= e($f['copied_to'] ?? '—') ?></td>
        <td class="xg-act"><?= $f['exists'] ? '<a class="btn btn-sm" href="' . e(url('backups/' . $f['id'] . '/download')) . '">Download</a>' : badge('Removed') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $files ? ' hidden' : '' ?> data-xg-empty>No backups yet.</p>
</div>
</div>
