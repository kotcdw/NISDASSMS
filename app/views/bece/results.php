<?php $yq = $year ? ['year' => $year['id']] : []; ?>
<div class="xgrid-box">
<div class="page-head">
    <div><h1>BECE results</h1></div>
    <div class="actions"><?php require __DIR__ . '/_year.php'; ?></div>
</div>
<?php require __DIR__ . '/_head.php'; ?>
<form method="post" action="<?= e(url('bece/results')) ?>">
    <?= csrf_field() ?><input type="hidden" name="year" value="<?= (int) ($year['id'] ?? 0) ?>">
    <div class="table-wrap"><table class="score-table">
        <thead><tr><th>Candidate</th><th>Index no. (or admission no.)</th><?php foreach ($subjects as $s): ?><th title="<?= e($s['name']) ?>" style="text-align:center"><?= e($s['code']) ?><?= in_array($s['code'], $core, true) ? '*' : '' ?></th><?php endforeach; ?><th class="num">Agg.</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $res = json_decode((string) $r['results'], true) ?: []; ?>
        <tr><td><?= e($r['last_name'] . ' ' . $r['first_name']) ?></td>
            <td><?= e($r['index_no'] ?: $r['admission_no']) ?></td>
            <?php foreach ($subjects as $s): ?><td><input name="g[<?= (int) $r['id'] ?>][<?= e($s['code']) ?>]" value="<?= e((string) ($res[$s['code']] ?? '')) ?>" inputmode="numeric" maxlength="1" style="width:2.6rem;text-align:center"></td><?php endforeach; ?>
            <td class="num"><strong><?= $r['aggregate'] !== null ? (int) $r['aggregate'] : '—' ?></strong></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php if (!$rows): ?><p class="xg-empty muted">No JHS 3 candidates for this year.</p><?php endif; ?>
    <?php if ($rows && can('bece.manage')): ?><div class="gate-actions" style="padding:1rem;border-top:1px solid var(--line)"><button class="btn btn-primary" type="submit">Save results</button> <span class="hint">* core subject</span></div><?php endif; ?>
</form>
</div>
