<?php $ct = 'canteen/plans'; $unbilled = count(array_filter($members, fn($m) => !$m['invoice_id'])); ?>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="xgrid-box" data-xgrid="meal-plan-members" data-xgrid-file="meal-plans-this-term">
<div class="page-head">
    <div><h1>Meal plans</h1></div>
    <div class="actions">
        <form method="get" action="<?= e(url('canteen/plans')) ?>" class="list-pick" data-autosubmit><select name="class" aria-label="Put students on a plan: choose a class"><option value="">Put a class on a plan…</option><?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($classId, $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select><noscript><button class="btn btn-sm">Show</button></noscript></form>
        <button type="button" class="btn btn-ghost" data-help="plans-box" aria-expanded="false">Plans &amp; prices</button>
        <?= export_grid_menu() ?>
        <?php if ($unbilled): ?><form method="post" action="<?= e(url('canteen/plans/bill')) ?>" data-confirm="Create meal-plan bills for <?= $unbilled ?> student(s)?"><?= csrf_field() ?><button class="btn btn-primary" type="submit">Bill <?= $unbilled ?> student(s)</button></form><?php endif; ?>
    </div>
</div>
<div id="plans-box" class="card list-form" hidden><h2>Plans</h2>
    <?php foreach ($plans as $pl): ?>
    <details style="margin-bottom:.5rem"><summary><strong><?= e($pl['name']) ?></strong> — <?= e(Fees::money($pl['price'])) ?> a term · <?= (int) $pl['n'] ?> students<?= (int) $pl['is_active'] ? '' : ' (not used)' ?></summary>
        <form method="post" action="<?= e(url('canteen/plans/save')) ?>" class="form" style="margin-top:.4rem"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $pl['id'] ?>">
            <div class="grid-3"><label>Name<input name="name" value="<?= e($pl['name']) ?>" required></label><label>Meals<input name="meals" value="<?= e($pl['meals'] ?? '') ?>"></label><label>Price per term<input name="price" value="<?= e($pl['price']) ?>" required></label></div>
            <label style="font-weight:400"><input type="checkbox" name="is_active" value="1"<?= (int) $pl['is_active'] ? ' checked' : '' ?>> In use</label><button class="btn btn-sm" type="submit">Save</button></form></details>
    <?php endforeach; ?>
    <form method="post" action="<?= e(url('canteen/plans/save')) ?>" class="form" style="margin-top:.8rem"><?= csrf_field() ?><h3 style="margin:0">New plan</h3>
        <div class="grid-3"><label>Name<input name="name" required></label><label>Meals<input name="meals" placeholder="e.g. Lunch, Monday to Friday"></label><label>Price per term (GH₵)<input name="price" required></label></div>
        <div class="gate-actions"><button class="btn btn-primary" type="submit">Add plan</button><button type="button" class="btn btn-ghost" data-help="plans-box">Close</button></div></form>
</div>
<?php if ($pupils): ?>
<form method="post" action="<?= e(url('canteen/plans/members')) ?>" class="card list-form" style="padding:0"><?= csrf_field() ?><input type="hidden" name="class_id" value="<?= (int) $classId ?>">
    <div class="table-wrap"><table><tr><th>Student</th><th>Allergy</th><th>Plan</th></tr>
    <?php foreach ($pupils as $p): ?><tr><td><?= e($p['last_name'] . ', ' . $p['first_name']) ?></td><td><?= trim((string) $p['allergies']) !== '' ? badge('Allergy: ' . $p['allergies'], 'bad') : '' ?></td>
        <td><select name="plan[<?= (int) $p['id'] ?>]" aria-label="Plan for <?= e($p['first_name']) ?>"><option value="0">No plan</option><?php foreach ($plans as $pl): if (!(int) $pl['is_active']) continue; ?><option value="<?= (int) $pl['id'] ?>"<?= selected($p['plan_id'], $pl['id']) ?>><?= e($pl['name']) ?> (<?= e(Fees::money($pl['price'])) ?>)</option><?php endforeach; ?></select></td></tr><?php endforeach; ?>
    </table></div><div class="gate-actions" style="padding:1rem"><button class="btn btn-primary" type="submit">Save</button><a class="btn btn-ghost" href="<?= e(url('canteen/plans')) ?>">Close</a></div>
</form>
<?php endif; ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['student' => ['Student'], 'class' => ['Class'], 'plan' => ['Plan'], 'bill' => ['Bill no.'], 'billst' => ['Bill status']]) ?></tr></thead>
    <tbody>
    <?php foreach ($members as $m): ?>
    <tr>
        <td data-c="student"><?= e($m['last_name'] . ', ' . $m['first_name']) ?></td>
        <td data-c="class"><?= e($m['class_name'] ?? '') ?></td>
        <td data-c="plan"><?= e($m['plan_name']) ?></td>
        <td data-c="bill"><?= e($m['invoice_no'] ?? '') ?></td>
        <td data-c="billst"><?= $m['invoice_no'] ? badge(ucfirst($m['inv_status']), $m['inv_status'] === 'paid' ? 'good' : 'warn') : badge('Not billed', 'neutral') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $members ? ' hidden' : '' ?> data-xg-empty>Nobody is on a plan this term yet.</p>
</div>
</div>
