<?php
$class = $R['class']; $term = $R['term']; $q = ['term' => $term['id']];
$ids = array_map(fn($p) => (int) $p['id'], $R['pupils']);
$pos = array_search($pid, $ids, true);
$p = $R['pupils'][$pos];
$pers = json_decode((string) ($R['remarks'][$pid]['personality'] ?? ''), true) ?: [];
$done = count(array_filter($ids, fn($i) => !empty(json_decode((string) ($R['remarks'][$i]['personality'] ?? ''), true))));
?>
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('reports', $q)) ?>">Report cards</a> / <a href="<?= e(url('reports/class/' . $class['id'] . '/remarks', $q)) ?>">Remarks — <?= e($class['name']) ?></a></div>
    <h1>Child's Personality Development Report</h1></div>
    <div class="actions"><a class="btn" href="<?= e(url('reports/class/' . $class['id'] . '/print', $q + ['pupil' => $pid])) ?>" target="_blank" rel="noopener">Preview this report</a></div>
</div>
<?php if (Results::released((int) $class['id'], (int) $term['id'])): ?><div class="alert alert-warning"><strong>Released to parents.</strong> Remarks are locked. To correct them, withdraw the release in <a href="<?= e(url('reports', $q)) ?>">Report cards</a> first.</div><?php endif; ?>
<div class="cols">
    <form method="post" action="<?= e(url('reports/class/' . $class['id'] . '/personality', $q)) ?>" class="card form">
        <?= csrf_field() ?><input type="hidden" name="pupil_id" value="<?= $pid ?>">
        <h2 style="margin-top:0"><?= e($p['last_name'] . ' ' . $p['first_name']) ?> <small class="muted">(<?= $pos + 1 ?> of <?= count($ids) ?>)</small></h2>
        <?php foreach ($traits as $i => $t): $sug = ReportController::TRAIT_SUGGESTIONS[$t] ?? []; ?>
            <?php if ($sug): ?><datalist id="dl-t<?= $i ?>"><?php foreach ($sug as $o): ?><option value="<?= e($o) ?>"><?php endforeach; ?></datalist><?php endif; ?>
            <label><?= e($t) ?> <input name="trait[<?= $i ?>]" maxlength="150" value="<?= e($pers[$t] ?? '') ?>"<?= $sug ? ' list="dl-t' . $i . '"' : '' ?>></label>
        <?php endforeach; ?>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <?php if (isset($ids[$pos + 1])): ?><button class="btn btn-primary" type="submit" name="next" value="1">Save &amp; next student →</button><?php endif; ?>
            <button class="btn<?= isset($ids[$pos + 1]) ? '' : ' btn-primary' ?>" type="submit">Save</button>
        </div>
    </form>
    <div class="card" style="padding:0">
        <h2 style="padding:1rem 1rem 0">Students</h2>
        <div class="table-wrap" style="max-height:70vh;overflow:auto"><table>
            <?php foreach ($R['pupils'] as $x): $xid = (int) $x['id']; $ok = !empty(json_decode((string) ($R['remarks'][$xid]['personality'] ?? ''), true)); ?>
            <tr<?= $xid === $pid ? ' style="background:var(--brand-soft)"' : '' ?>><td><a href="<?= e(url('reports/class/' . $class['id'] . '/personality', $q + ['pupil' => $xid])) ?>"><?= e($x['last_name'] . ', ' . $x['first_name']) ?></a></td><td><?= $ok ? badge('Done', 'good') : '<small class="muted">—</small>' ?></td></tr>
            <?php endforeach; ?>
        </table></div>
    </div>
</div>
