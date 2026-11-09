<?php /* List columns: expects $on (list => shown column keys). */ ?>
<div class="page-head"><div><h1>List columns</h1></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<form method="post" action="<?= e(url('setup/columns')) ?>" class="form">
    <?= csrf_field() ?>
    <?php foreach (ListColumns::LISTS as $l => [$listTitle, , $cols]): ?>
    <section class="card">
        <h2 style="margin-top:0"><?= e($listTitle) ?></h2>
        <p class="muted">Tick the columns everyone sees on this list.</p>
        <div class="col-picks">
        <?php foreach ($cols as $k => [$label]): $locked = ListColumns::locked($l, $k); ?>
            <label><input type="checkbox" name="cols[<?= e($l) ?>][]" value="<?= e($k) ?>"<?= in_array($k, $on[$l], true) ? ' checked' : '' ?><?= $locked ? ' disabled' : '' ?>> <?= e($label) ?><?= $locked ? ' <small class="muted">(always shown)</small>' : '' ?></label>
        <?php endforeach; ?>
        </div>
    </section>
    <?php endforeach; ?>
    <div class="actions" style="margin-top:1rem"><button class="btn btn-primary" type="submit">Save columns</button></div>
</form>
