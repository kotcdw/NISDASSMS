<?php $yq = $year ? ['year' => $year['id']] : []; ?>
<div class="xgrid-box" data-xgrid="bece" data-xgrid-file="bece-registration<?= $year ? '-' . e($year['name']) : '' ?>">
<div class="page-head">
    <div><h1>BECE registration</h1></div>
    <div class="actions">
        <?php require __DIR__ . '/_year.php'; ?>
        <?= export_grid_menu() ?>
        <details class="menu-btn"><summary class="btn">More ▾</summary><div>
            <a href="<?= e(url('bece/print', $yq)) ?>" target="_blank">Print list</a>
            <a href="<?= e(url('bece/photos', $yq)) ?>">Download photos (zip)</a>
            <?= export_links(url('bece/export', $yq), 'Full BECE file —') ?>
        </div></details>
    </div>
</div>
<?php require __DIR__ . '/_head.php'; ?>
<form method="post" action="<?= e(url('bece')) ?>">
    <?= csrf_field() ?><input type="hidden" name="year" value="<?= (int) ($year['id'] ?? 0) ?>">
    <div class="xg-scroll"><table class="xgrid">
        <thead><tr><?= list_th(['name' => ['Candidate'], 'adm' => ['Admission no.'], 'class' => ['Class'], 'sex' => ['Sex'], 'dob' => ['Date of birth', 'date'], 'nia' => ['Ghana Card (NIA) no.'], 'index' => ['Index no.'], 'check' => ['Check']]) ?></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $nia = (string) ($r['nia_no'] ?? ''); $idx = (string) ($r['index_no'] ?? ''); ?>
        <tr>
            <td data-c="name"><a href="<?= e(url('pupils/' . $r['id'])) ?>"><strong><?= e(strtoupper($r['last_name'])) ?></strong> <?= e(trim($r['first_name'] . ' ' . ($r['middle_name'] ?? ''))) ?></a></td>
            <td data-c="adm"><?= e($r['admission_no']) ?></td>
            <td data-c="class"><?= e($r['class_name']) ?></td>
            <td data-c="sex"><?= $r['gender'] === 'male' ? 'M' : 'F' ?></td>
            <td data-c="dob" data-s="<?= e((string) $r['date_of_birth']) ?>"><?= e(date('d/m/Y', strtotime($r['date_of_birth']))) ?></td>
            <td data-c="nia" data-s="<?= e($nia) ?>"><span hidden><?= e($nia) ?></span><input name="nia_no[<?= (int) $r['id'] ?>]" value="<?= e($nia) ?>" placeholder="GHA-000000000-0" aria-label="Ghana Card no." style="width:11rem"></td>
            <td data-c="index" data-s="<?= e($idx) ?>"><span hidden><?= e($idx) ?></span><input name="index_no[<?= (int) $r['id'] ?>]" value="<?= e($idx) ?>" aria-label="Index no." style="width:9rem"></td>
            <td data-c="check"><?= $r['problems'] ? implode('<span hidden> · </span> ', array_map(fn($p) => badge($p, 'warn'), $r['problems'])) : badge('OK', 'good') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No students are enrolled in JHS 3 for this year.</p>
    </div>
    <?php if ($rows && can('bece.manage')): ?><div class="gate-actions" style="padding:1rem;border-top:1px solid var(--line)"><button class="btn btn-primary" type="submit">Save numbers</button></div><?php endif; ?>
</form>
</div>
