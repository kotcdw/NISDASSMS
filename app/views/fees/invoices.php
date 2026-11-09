<?php
$status = (string) ($_GET['status'] ?? 'all');
$tabs = [];
foreach (['all' => 'All bills', 'owing' => 'Owing', 'paid' => 'Paid', 'unpaid' => 'Unpaid', 'part' => 'Part paid', 'cancelled' => 'Cancelled'] as $k => $l) {
    $tabs[$k] = [$l, url('fees/invoices', array_filter(['status' => $k === 'all' ? '' : $k, 'term' => $_GET['term'] ?? '', 'class' => $_GET['class'] ?? '', 'kind' => $_GET['kind'] ?? '']))];
}
if (!isset($tabs[$status])) $status = 'all';
?>
<div class="xgrid-box" data-xgrid="fee-bills" data-xgrid-file="fee-bills">
<div class="page-head">
    <div><h1>Bills</h1></div>
    <div class="actions">
        <?= export_grid_menu() ?>
        <?php if (can('fees.manage')): ?><a class="btn btn-primary" href="<?= e(url('fees/generate')) ?>">+ Make term bills</a><?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<?= card_tabs($tabs, $status, 4, 'Bills to show') ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['bill' => ['Bill'], 'student' => ['Student'], 'adm' => ['Admission no.'], 'class' => ['Class'], 'for' => ['For'], 'total' => ['Total (GH₵)', 'num', 'num'], 'paid' => ['Paid (GH₵)', 'num', 'num'], 'bal' => ['Balance (GH₵)', 'num', 'num'], 'status' => ['Status'], 'due' => ['Due', 'date']]) ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $i): [$sl, $st] = Fees::STATUS[$i['status']]; $bal = Fees::balance($i); $late = $bal > 0 && $i['due_date'] && $i['due_date'] < date('Y-m-d'); ?>
    <tr>
        <td data-c="bill"><a href="<?= e(url('fees/invoices/' . $i['id'])) ?>"><?= e($i['invoice_no']) ?></a></td>
        <td data-c="student"><a href="<?= e(url('fees/pupil/' . $i['pupil_id'])) ?>"><?= e(full_name($i)) ?></a></td>
        <td data-c="adm"><?= e($i['admission_no']) ?></td>
        <td data-c="class"><?= e($i['class_name'] ?? '') ?></td>
        <td data-c="for"><?= e($i['title']) ?></td>
        <td data-c="total" class="num" data-s="<?= e((string) $i['total']) ?>"><?= e(number_format((float) $i['total'], 2)) ?></td>
        <td data-c="paid" class="num" data-s="<?= e((string) $i['paid']) ?>"><?= e(number_format((float) $i['paid'], 2)) ?></td>
        <td data-c="bal" class="num" data-s="<?= e((string) $bal) ?>"><strong><?= e(number_format($bal, 2)) ?></strong></td>
        <td data-c="status"><?= badge($sl, $st) ?></td>
        <td data-c="due" data-s="<?= e((string) $i['due_date']) ?>"<?= $late ? ' style="color:var(--bad)"' : '' ?>><?= e(fmt_date($i['due_date'])) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No bills here.</p>
</div>
</div>
<div class="list-pager"><?= pager_links($pager) ?></div>
