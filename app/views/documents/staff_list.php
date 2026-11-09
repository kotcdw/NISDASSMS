<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('documents')) ?>">Documents</a> /</div><h1>Staff files</h1></div>
</div>
<div class="card" style="padding:0"><div class="table-wrap"><table>
    <tr><th>Staff</th><th>Position</th><th class="num">Files</th><th>Next expiry</th></tr>
    <?php foreach ($rows as $s): ?><tr>
        <td><a href="<?= e(url('documents/staff/' . $s['id'])) ?>"><strong><?= e($s['name']) ?></strong></a><br><small class="muted"><?= e($s['staff_no']) ?></small></td>
        <td><?= e($s['position']) ?></td><td class="num"><?= (int) $s['files'] ?></td>
        <td><?= $s['next_expiry'] ? ($s['next_expiry'] < date('Y-m-d') ? badge('Expired ' . fmt_date($s['next_expiry'], 'j M Y'), 'bad') : e(fmt_date($s['next_expiry'], 'j M Y'))) : '<span class="muted">—</span>' ?></td>
    </tr><?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="4" class="muted">No staff records yet (Payroll → Staff).</td></tr><?php endif; ?>
</table></div></div>
