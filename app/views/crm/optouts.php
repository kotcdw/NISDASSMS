<?php $ct = 'marketing/optouts'; $openForm = old('phone') !== ''; ?>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="xgrid-box" data-xgrid="optouts" data-xgrid-file="do-not-message">
<div class="page-head">
    <div><h1>Do not message</h1></div>
    <div class="actions"><?= export_grid_menu() ?><button type="button" class="btn btn-primary" data-help="optout-form" aria-expanded="<?= $openForm ? 'true' : 'false' ?>">+ Add a number</button></div>
</div>
<form id="optout-form" method="post" action="<?= e(url('marketing/optouts')) ?>" class="card form list-form"<?= $openForm ? '' : ' hidden' ?>><?= csrf_field() ?>
    <div class="grid-2"><label class="required">Phone<input name="phone" required inputmode="tel" value="<?= e(old('phone')) ?>"></label><label>Reason<input name="reason" maxlength="120" placeholder="e.g. Called the office to stop"></label></div>
    <div class="gate-actions"><button class="btn btn-primary" type="submit">Add</button><button type="button" class="btn btn-ghost" data-help="optout-form">Cancel</button></div>
</form>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['phone' => ['Phone'], 'reason' => ['Reason'], 'added' => ['Added', 'date'], 'by' => ['By']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $o): ?><tr><td data-c="phone"><?= e($o['phone']) ?></td><td data-c="reason"><?= e($o['reason'] ?? '') ?></td><td data-c="added" data-s="<?= e($o['created_at']) ?>"><?= e(fmt_date($o['created_at'])) ?></td><td data-c="by"><?= e($o['by_name'] ?? '') ?></td>
        <td class="xg-act"><form method="post" action="<?= e(url('marketing/optouts')) ?>"><?= csrf_field() ?><input type="hidden" name="phone" value="<?= e($o['phone']) ?>"><input type="hidden" name="remove" value="1"><button class="btn btn-sm btn-ghost" type="submit">Remove</button></form></td></tr><?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>Nobody on the list.</p>
</div>
</div>
