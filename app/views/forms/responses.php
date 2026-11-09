<?php
$shown = array_slice($fields, 0, 4);
$cols = ['no' => ['#', 'num', 'num'], 'when' => ['Submitted', 'date']];
if ($form['collect_contact']) { $cols += ['name' => ['Name'], 'phone' => ['Phone']]; }
foreach ($shown as $f) { $cols['f' . (int) $f['id']] = [mb_strimwidth($f['label'], 0, 30, '…')]; }
?>
<div class="xgrid-box" data-xgrid="form-responses" data-xgrid-file="responses-<?= (int) $form['id'] ?>">
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('forms')) ?>">Form Builder</a> / <a href="<?= e(url('forms/' . $form['id'])) ?>"><?= e($form['title']) ?></a> /</div>
    <h1>Responses</h1></div>
    <div class="actions">
        <?= export_menu(url('forms/' . $form['id'] . '/responses/export'), 'All answers', 'btn btn-ghost') ?>
        <?= export_grid_menu() ?>
    </div>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th($cols) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
    <tr>
        <td data-c="no" class="num"><?= (int) $r['id'] ?></td>
        <td data-c="when" data-s="<?= e((string) $r['created_at']) ?>" style="white-space:nowrap"><?= e(fmt_date($r['created_at'], 'd M H:i')) ?></td>
        <?php if ($form['collect_contact']): ?><td data-c="name"><?= e($r['respondent_name'] ?? $r['user_name']) ?></td><td data-c="phone"><?= e($r['respondent_phone']) ?></td><?php endif; ?>
        <?php foreach ($shown as $f): $a = $answers[$r['id']][$f['id']] ?? null; ?>
            <td data-c="f<?= (int) $f['id'] ?>"><?= $a ? ($f['type'] === 'file' ? 'File: ' . e($a['file_name']) : e(mb_strimwidth(FormDef::display($a['value'], $f['type']), 0, 60, '…'))) : '' ?></td>
        <?php endforeach; ?>
        <td class="xg-act"><a class="btn btn-sm" href="<?= e(url('forms/' . $form['id'] . '/responses/' . $r['id'])) ?>">View</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No responses yet.</p>
</div>
</div>
<div class="list-pager"><?= pager_links($pager) ?></div>

<?php if ($tallies && $pager['total']): ?>
<div class="grid-2" style="margin-top:1.25rem">
    <?php foreach ($tallies as $t): $max = max(1, max($t['counts'] ?: [0])); ?>
    <div class="card" style="margin:0">
        <h3><?= e($t['field']['label']) ?></h3>
        <?php foreach ($t['counts'] as $opt => $n): ?>
            <div style="display:grid;grid-template-columns:minmax(0,1fr) 120px 2.5rem;gap:.5rem;align-items:center;font-size:.85rem;margin-bottom:.3rem">
                <span><?= e($opt) ?></span>
                <div class="meter"><span style="width:<?= round($n * 100 / $max) ?>%"></span></div>
                <strong style="text-align:right"><?= (int) $n ?></strong>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
