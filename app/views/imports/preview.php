<?php $cols = array_keys(Importer::KINDS[$job['kind']]['columns']); $show = array_slice($cols, 0, 7); ?>
<div class="page-head">
    <div><p class="breadcrumb"><a href="<?= e(url('imports')) ?>">Import from Excel</a></p><h1>Check: <?= e(Importer::KINDS[$job['kind']]['label']) ?></h1></div>
    <div class="actions">
        <form method="post" action="<?= e(url('imports/cancel')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn-ghost" type="submit">Cancel</button></form>
        <?php if ($ok): ?><form method="post" action="<?= e(url('imports/commit')) ?>" class="inline" data-confirm="Import <?= $ok ?> row(s) now?"><?= csrf_field() ?><button class="btn btn-primary" type="submit">Import <?= $ok ?> row(s)</button></form><?php endif; ?>
    </div>
</div>
<div class="card" style="padding:0">
    <div class="table-wrap"><table>
        <tr><th>Row</th><?php foreach ($show as $c): ?><th><?= e(str_replace('_', ' ', $c)) ?></th><?php endforeach; ?><th>Check</th></tr>
        <?php foreach ($checked as $c): $d = $c['data']; ?>
        <tr<?= $c['errors'] ? ' style="background:var(--bad-soft)"' : '' ?>>
            <td><?= e($c['line']) ?></td>
            <?php foreach ($show as $col): $val = $d[$col] ?? ($col === 'class' ? ($d['class_name'] ?? '') : ''); ?><td><small><?= e(is_scalar($val) ? (string) $val : '') ?></small></td><?php endforeach; ?>
            <td><?= $c['errors'] ? '<small style="color:var(--bad)">' . e(implode('; ', $c['errors'])) . '</small>' : badge('OK', 'good') ?></td>
        </tr>
        <?php endforeach; ?>
    </table></div>
</div>
