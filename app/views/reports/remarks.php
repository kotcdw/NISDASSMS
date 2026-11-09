<?php $class = $R['class']; $term = $R['term']; $q = ['term' => $term['id']]; $canHead = can('reports.head'); ?>
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('reports', $q)) ?>">Report cards</a> / <?= e($term['year_name'] . ' · ' . $term['name']) ?></div>
    <h1>Remarks — <?= e($class['name']) ?></h1></div>
    <div class="actions"><?php if (setting('report_personality', '1') === '1'): ?><a class="btn" href="<?= e(url('reports/class/' . $class['id'] . '/personality', $q)) ?>">Personality report (page 2)</a><?php endif; ?> <a class="btn" href="<?= e(url('reports/class/' . $class['id'] . '/print', $q)) ?>" target="_blank" rel="noopener">Preview report cards</a></div>
</div>
<?php if (Results::released((int) $class['id'], (int) $term['id'])): ?><div class="alert alert-warning"><strong>Released to parents.</strong> Remarks are locked. To correct them, withdraw the release in <a href="<?= e(url('reports', $q)) ?>">Report cards</a> first.</div><?php endif; ?>
<datalist id="dl-remark">
    <option value="A quick learner with excellent comprehension skills. Reads well, understands and can explain topics in own words."><option value="Well done. Keep the lamp burning."><option value="An excellent term. Keep it up!"><option value="Very good performance. Aim higher next term.">
    <option value="Good work, but can do better with more effort."><option value="Fair performance. Must take studies more seriously.">
    <option value="Needs to put in more effort, especially in Mathematics and English."><option value="Well behaved and hardworking.">
</datalist>
<form method="post" action="<?= e(url('reports/class/' . $class['id'] . '/remarks', $q)) ?>">
    <?= csrf_field() ?>
    <div class="card" style="padding:0">
        <div class="table-wrap"><table class="remarks-table">
            <tr><th>Student</th><th class="num">Avg / Pos.</th><th>Class teacher’s comments</th><?= $isLastTerm ? '<th>Promotion</th>' : '' ?><?= $canHead ? '<th>Head of school’s comments</th>' : '' ?></tr>
            <?php foreach ($R['pupils'] as $p): $pid = (int) $p['id']; $rm = $R['remarks'][$pid] ?? []; $sm = $R['summary'][$pid]; $n = "r[$pid]"; ?>
            <tr>
                <td style="white-space:nowrap"><strong><?= e($p['last_name'] . ', ' . $p['first_name']) ?></strong></td>
                <td class="num"><?= Results::fmt($sm['average']) ?><br><small><?= e(Results::ordinal($sm['position'])) ?></small></td>
                <td><input name="<?= $n ?>[teacher_remark]" list="dl-remark" maxlength="255" value="<?= e($rm['teacher_remark'] ?? '') ?>" style="min-width:340px" aria-label="Class teacher's comments"><?php if (!$isLastTerm): ?><input type="hidden" name="<?= $n ?>[promotion]" value="<?= e($rm['promotion'] ?? '') ?>"><?php endif; ?></td>
                <?php if ($isLastTerm): ?>
                <td><select name="<?= $n ?>[promotion]" aria-label="Promotion">
                    <?php foreach (['' => '—', 'promoted' => 'Promoted', 'on_trial' => 'Promoted on trial', 'repeated' => 'Repeated'] as $k => $l): ?><option value="<?= $k ?>"<?= selected($k, $rm['promotion'] ?? '') ?>><?= e($l) ?></option><?php endforeach; ?>
                </select></td>
                <?php endif; ?>
                <?php if ($canHead): ?><td><input name="<?= $n ?>[head_remark]" list="dl-remark" maxlength="255" value="<?= e($rm['head_remark'] ?? '') ?>" style="min-width:260px" aria-label="Head of school's comments"></td><?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </table></div>
    </div>
    <button class="btn btn-primary" type="submit">Save remarks</button>
</form>
