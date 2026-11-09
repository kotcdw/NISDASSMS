<?php $today = date('Y-m-d'); ?>
<div class="xgrid-box" data-xgrid="fee-debtors" data-xgrid-file="fee-debtors">
<div class="page-head">
    <div><h1>Who owes</h1></div>
    <div class="actions">
        <?= export_grid_menu() ?>
        <?php if (can('fees.manage') && $rows): ?>
        <form method="post" action="<?= e(url('fees/reminders')) ?>" class="inline" data-confirm="Send a polite reminder SMS with the pay link to the parents of every student owing at least GH₵ <?= e((string) $f['min']) ?><?= $f['class'] ? ' in this class' : '' ?>? Each family gets at most one reminder a day; nothing is sent during Sabbath hours.">
            <?= csrf_field() ?><input type="hidden" name="class" value="<?= (int) $f['class'] ?>"><input type="hidden" name="min" value="<?= e((string) $f['min']) ?>">
            <button class="btn btn-primary" type="submit">Send reminders</button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['student' => ['Student'], 'adm' => ['Admission no.'], 'class' => ['Class'], 'bal' => ['Balance (GH₵)', 'num', 'num'], 'bills' => ['Bills', 'num', 'num'], 'oldest' => ['Oldest due', 'date'], 'last' => ['Last payment', 'date'], 'guardian' => ['Guardian'], 'phone' => ['Phone']]) ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
    <tr>
        <td data-c="student"><a href="<?= e(url('fees/pupil/' . $r['id'])) ?>"><?= e(full_name($r)) ?></a></td>
        <td data-c="adm"><?= e($r['admission_no']) ?></td>
        <td data-c="class"><?= e($r['class_name'] ?? '') ?></td>
        <td data-c="bal" class="num" data-s="<?= e((string) $r['balance']) ?>"><strong style="color:var(--bad)"><?= e(number_format((float) $r['balance'], 2)) ?></strong></td>
        <td data-c="bills" class="num"><?= (int) $r['bills'] ?></td>
        <td data-c="oldest" data-s="<?= e((string) $r['oldest_due']) ?>"<?= $r['oldest_due'] && $r['oldest_due'] < $today ? ' style="color:var(--bad)"' : '' ?>><?= e(fmt_date($r['oldest_due'])) ?></td>
        <td data-c="last" data-s="<?= e((string) $r['last_paid']) ?>"><?= $r['last_paid'] ? e(fmt_date($r['last_paid'])) : '<span class="muted">Never</span>' ?></td>
        <td data-c="guardian"><?= e($r['guardian_name'] ?? '') ?></td>
        <td data-c="phone"><?= e($r['guardian_phone'] ?? '') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>Nobody owes at this level.</p>
</div>
</div>
