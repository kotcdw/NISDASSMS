<?php $ct = 'canteen/menu'; $edit = can('canteen.manage'); ?>
<div class="page-head">
    <div><h1>Canteen menu</h1></div>
    <div class="actions"><a class="btn btn-ghost" href="<?= e(url('canteen/menu', ['week' => date('Y-m-d', strtotime($mon . ' -7 days'))])) ?>">← Previous week</a><a class="btn btn-ghost" href="<?= e(url('canteen/menu', ['week' => date('Y-m-d', strtotime($mon . ' +7 days'))])) ?>">Next week →</a></div>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="cm-week">
<?php for ($i = 0; $i < 5; $i++): $d = date('Y-m-d', strtotime($mon . " +$i days")); $did = 'cm-edit-' . $i; ?>
    <div class="card cm-day<?= $d === date('Y-m-d') ? ' is-today' : '' ?>">
        <div class="cm-day-head"><h3><?= e(date('D j M', strtotime($d))) ?></h3><?php if ($edit): ?><button type="button" class="btn btn-sm" data-help="<?= $did ?>" aria-expanded="false">Edit</button><?php endif; ?></div>
        <?php foreach (Canteen::MEALS as $mk => $ml): $names = array_column($menu[$d][$mk] ?? [], 'name'); ?>
        <p class="cm-meal"><strong><?= e($ml) ?></strong><span><?= $names ? e(implode(', ', $names)) : '<span class="muted">—</span>' ?></span></p>
        <?php endforeach; ?>
        <?php if ($edit): ?>
        <div id="<?= $did ?>" class="cm-edit" hidden>
            <?php foreach (Canteen::MEALS as $mk => $ml): $sel = array_map('intval', array_column($menu[$d][$mk] ?? [], 'item_id')); ?>
            <form method="post" action="<?= e(url('canteen/menu')) ?>"><?= csrf_field() ?><input type="hidden" name="date" value="<?= e($d) ?>"><input type="hidden" name="meal" value="<?= e($mk) ?>">
                <label><?= e($ml) ?>
                <select name="items[]" multiple size="3" aria-label="<?= e($ml . ' ' . $d) ?>"><?php foreach ($items as $it): ?><option value="<?= (int) $it['id'] ?>"<?= in_array((int) $it['id'], $sel, true) ? ' selected' : '' ?>><?= e($it['name']) ?></option><?php endforeach; ?></select></label>
                <button class="btn btn-sm btn-primary" type="submit">Save <?= e(strtolower($ml)) ?></button>
            </form>
            <?php endforeach; ?>
            <button type="button" class="btn btn-sm btn-ghost" data-help="<?= $did ?>">Close</button>
        </div>
        <?php endif; ?>
    </div>
<?php endfor; ?>
</div>
