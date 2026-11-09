<?php $gt = 'governance/members'; $mg = can('governance.manage'); ?>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="xgrid-box" data-xgrid="board-members" data-xgrid-file="board-members">
<div class="page-head">
    <div><h1>Board members</h1></div>
    <div class="actions"><?= export_grid_menu() ?><?php if ($mg): ?><button type="button" class="btn btn-primary" data-help="new-member" aria-expanded="false">+ Add a board member</button><?php endif; ?></div>
</div>
<?php if ($mg): ?>
<form id="new-member" method="post" action="<?= e(url('governance/members')) ?>" class="card form list-form" hidden><?= csrf_field() ?>
    <h2>Add a board member</h2>
    <div class="grid-2"><label class="required">Name <input name="name" required></label><label>Position <input name="position" placeholder="e.g. Chairman, Secretary, Member"></label></div>
    <div class="grid-2"><label>Phone <input name="phone" placeholder="0244123456"></label><label>Email <input type="email" name="email"></label></div>
    <div class="gate-actions"><button class="btn btn-primary" type="submit">Add member</button><button type="button" class="btn btn-ghost" data-help="new-member">Cancel</button></div>
</form>
<?php endif; ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['name' => ['Name'], 'position' => ['Position'], 'phone' => ['Phone'], 'email' => ['Email'], 'att' => ['Attendance %', 'num', 'num'], 'present' => ['Meetings attended', 'num', 'num'], 'status' => ['Status']]) ?><?= $mg ? '<th class="xg-act"></th>' : '' ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $b): $pct = (int) $b['marked'] ? round($b['attended'] * 100 / $b['marked']) : null; $st = (int) $b['is_active'] ? 'Active' : 'Former';
        $keys = ['name' => $b['name'], 'position' => $b['position'] ?? '', 'phone' => $b['phone'] ?? '', 'email' => $b['email'] ?? '', 'att' => $pct ?? '', 'present' => (int) $b['attended'], 'status' => $st]; ?>
    <tr>
        <td data-c="name"><?= e($b['name']) ?></td><td data-c="position"><?= e($b['position'] ?? '') ?></td><td data-c="phone"><?= e($b['phone'] ?? '') ?></td><td data-c="email"><?= e($b['email'] ?? '') ?></td>
        <td data-c="att" class="num" data-s="<?= $pct ?? '' ?>"><?= $pct !== null ? $pct . '%' : '—' ?></td>
        <td data-c="present" class="num" data-s="<?= (int) $b['attended'] ?>"><?= (int) $b['marked'] ? (int) $b['attended'] . ' of ' . (int) $b['marked'] : '' ?></td>
        <td data-c="status" data-s="<?= $st ?>"><?= (int) $b['is_active'] ? badge('Active', 'good') : badge('Former', 'neutral') ?></td>
        <?php if ($mg): ?><td class="xg-act"><button type="button" class="btn btn-sm" data-help="edit-bm<?= (int) $b['id'] ?>" aria-expanded="false">Edit</button></td><?php endif; ?>
    </tr>
    <?php if ($mg): ?>
    <tr id="edit-bm<?= (int) $b['id'] ?>" class="xg-edit" hidden>
        <?php /* hidden copies of the sort values keep this row directly under its member when the list is sorted */ foreach ($keys as $k => $v): ?><td data-c="<?= $k ?>" data-s="<?= e((string) $v) ?>" hidden></td><?php endforeach; ?>
        <td colspan="8">
            <form method="post" action="<?= e(url('governance/members/' . $b['id'])) ?>" class="form" style="margin:0"><?= csrf_field() ?>
                <div class="grid-2"><label class="required">Name <input name="name" value="<?= e($b['name']) ?>" required></label><label>Position <input name="position" value="<?= e($b['position'] ?? '') ?>" placeholder="e.g. Chairman, Secretary, Member"></label></div>
                <div class="grid-2"><label>Phone <input name="phone" value="<?= e($b['phone'] ?? '') ?>" placeholder="0244123456"></label><label>Email <input type="email" name="email" value="<?= e($b['email'] ?? '') ?>"></label></div>
                <p style="margin:0 0 .8rem"><label style="margin:0;font-weight:400"><input type="checkbox" name="is_active" value="1"<?= checked((int) $b['is_active']) ?>> Still on the board (active)</label></p>
                <div class="gate-actions"><button class="btn btn-primary" type="submit">Save</button><button type="button" class="btn btn-ghost" data-help="edit-bm<?= (int) $b['id'] ?>">Cancel</button></div>
            </form>
        </td>
    </tr>
    <?php endif; ?>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No board members recorded yet.</p>
</div>
</div>
