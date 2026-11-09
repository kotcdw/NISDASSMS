<div class="page-head"><div><h1>Parent portal (preview)</h1></div>
    <div class="actions"><form method="get" action="<?= e(url('portal')) ?>" class="list-pick" role="search"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Find a parent or student" aria-label="Find a parent (name, phone, student name or admission no.)"></form></div>
</div>
<div class="card" style="padding:0"><div class="table-wrap"><table>
    <tr><th>Parent / guardian</th><th>Phone</th><th>Children</th><th></th></tr>
    <?php foreach ($list as $g): ?>
    <tr><td><?= e($g['name']) ?></td><td><?= e($g['phone']) ?></td><td><?= e($g['children']) ?></td><td><a class="btn btn-sm" href="<?= e(url('portal', ['guardian' => $g['id']])) ?>">View as this parent</a></td></tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="4" class="muted">No parents found<?= $q !== '' ? ' for "' . e($q) . '"' : ' — add students with their parents first' ?>.</td></tr><?php endif; ?>
</table></div></div>
