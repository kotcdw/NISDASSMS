<?php
$st = $a['status'] ?? null;
$done = $st === 'completed';
$selfEdit = $own && $a && !$done;
$headEdit = $canHead && $a && !$done;
$showSelf = $a && ($own || $a['self_submitted_at'] || $canHead);
$showHead = $a && ($done || $canHead);
$score = fn($cid, $who) => $a['scores'][(int) $cid][$who . '_score'] ?? null;
$ratingSelect = function (string $who, array $c, bool $edit) use ($score): string {
    $o = old($who, null);
    $v = is_array($o) && isset($o[$c['id']]) && $o[$c['id']] !== '' ? $o[$c['id']] : $score($c['id'], $who);
    if (!$edit) {
        return $v === null ? '<span class="muted">—</span>' : '<strong>' . (int) $v . '</strong> <small class="muted">' . e(Teachers::RATINGS[(int) $v] ?? '') . '</small>';
    }
    $h = '<select name="' . e($who) . '[' . (int) $c['id'] . ']" aria-label="' . e($c['name']) . '" style="min-width:140px"><option value="">—</option>';
    foreach (Teachers::RATINGS as $n => $w) {
        $h .= '<option value="' . $n . '"' . ((string) $v === (string) $n ? ' selected' : '') . '>' . $n . ' · ' . e($w) . '</option>';
    }
    return $h . '</select>';
};
$selfMean = $a ? Teachers::mean(array_map(fn($c) => $score($c['id'], 'self'), $criteria)) : null;
?>
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('teachers/' . $t['id'], ['term' => $term['id']])) ?>"><?= e($t['name']) ?></a> /</div>
    <h1>Appraisal · <?= e($term['year_name'] . ' ' . $term['name']) ?></h1>
    <p class="muted"><?= $a ? badge(Teachers::STATUS[$st][0], Teachers::STATUS[$st][1]) : 'Not opened' ?><?= $done && $a['appraiser_name'] ? ' · rated by ' . e($a['appraiser_name']) . ' on ' . e(date('j M Y', strtotime($a['completed_at']))) : '' ?></p></div>
    <div class="actions">
        <?php if ($a): ?><a class="btn btn-ghost" href="<?= e(url('teachers/' . $t['id'] . '/appraisal/print', ['term' => $term['id']])) ?>" target="_blank" rel="noopener">Print</a><?php endif; ?>
        <?php if ($done && $canHead): ?>
            <form method="post" action="<?= e(url('teachers/' . $t['id'] . '/appraisal/reopen')) ?>" class="inline" data-confirm="Reopen this appraisal for changes? The teacher's acknowledgement will be cleared.">
                <?= csrf_field() ?><input type="hidden" name="term" value="<?= (int) $term['id'] ?>"><button class="btn btn-ghost" type="submit">Reopen</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if (!$a): ?>
    <div class="alert alert-info">The appraisal for this term has not been opened yet.<?= $own ? ' The head teacher opens it; you will get a notice when it is your turn to do the self-assessment.' : '' ?></div>
    <?php if ($canHead): ?>
        <form method="post" action="<?= e(url('teachers/' . $t['id'] . '/appraisal/start')) ?>"><?= csrf_field() ?><input type="hidden" name="term" value="<?= (int) $term['id'] ?>"><button class="btn btn-primary" type="submit">Open appraisal for <?= e($t['name']) ?></button></form>
    <?php endif; ?>
<?php else: ?>

<?php if ($canHead && $st === 'open'): ?><div class="alert alert-warning"><?= e($t['name']) ?> has not sent the self-assessment yet. You can still prepare your rating.</div><?php endif; ?>
<?php if ($own && $st === 'self_done'): ?><div class="alert alert-success">Your self-assessment was sent on <?= e(date('j M Y', strtotime($a['self_submitted_at']))) ?>. The head will now rate you. You can still change it until then.</div><?php endif; ?>

<form method="post" action="<?= e(url('teachers/' . $t['id'] . '/appraisal/' . ($selfEdit ? 'self' : 'head'))) ?>" class="form">
    <?= csrf_field() ?><input type="hidden" name="term" value="<?= (int) $term['id'] ?>">
    <div class="card" style="padding:0"><div class="table-wrap"><table>
        <thead><tr><th>What is assessed</th><?php if ($showSelf): ?><th>Teacher's own rating</th><?php endif; ?><?php if ($showHead): ?><th>Head's rating</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($criteria as $c): ?>
            <tr>
                <td><strong><?= e($c['name']) ?></strong><?php if ($c['description']): ?><br><small class="muted"><?= e($c['description']) ?></small><?php endif; ?></td>
                <?php if ($showSelf): ?><td><?= $ratingSelect('self', $c, $selfEdit) ?></td><?php endif; ?>
                <?php if ($showHead): ?><td><?= $ratingSelect('head', $c, $headEdit) ?></td><?php endif; ?>
            </tr>
        <?php endforeach; ?>
        <tr><td><strong>Average</strong></td><?php if ($showSelf): ?><td><strong><?= $selfMean === null ? '—' : e(Results::fmt($selfMean)) . '/5' ?></strong></td><?php endif; ?><?php if ($showHead): ?><td><strong><?= $a['overall'] === null ? '—' : e(Results::fmt((float) $a['overall'])) . '/5 · ' . e(Teachers::ratingWord((float) $a['overall'])) ?></strong></td><?php endif; ?></tr>
        </tbody>
    </table></div></div>

    <div class="grid-2">
        <?php if ($showSelf): ?>
        <div class="card">
            <h2>Teacher's comments</h2>
            <?php if ($selfEdit): ?>
                <label>What went well this term, what was difficult, and support you need
                    <textarea name="self_comment" rows="6" maxlength="3000"><?= e(old('self_comment', (string) ($a['self_comment'] ?? ''))) ?></textarea></label>
            <?php else: ?><p style="white-space:pre-line"><?= $a['self_comment'] ? e($a['self_comment']) : '<span class="muted">No comment.</span>' ?></p><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($showHead): ?>
        <div class="card">
            <h2>Head's assessment</h2>
            <?php foreach (['strengths' => 'Strengths', 'improvements' => 'Areas to improve', 'targets' => 'Targets for next term', 'head_comment' => 'General comment'] as $k => $lab): ?>
                <?php if ($headEdit): ?>
                    <label><?= e($lab) ?><textarea name="<?= $k ?>" rows="3" maxlength="3000"><?= e(old($k, (string) ($a[$k] ?? ''))) ?></textarea></label>
                <?php elseif ($a[$k]): ?>
                    <p style="margin:0 0 .7rem"><strong><?= e($lab) ?></strong><br><span style="white-space:pre-line"><?= e($a[$k]) ?></span></p>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($selfEdit): ?>
        <div class="actions" style="display:flex;gap:.5rem">
            <button class="btn" type="submit">Save draft</button>
            <button class="btn btn-primary" type="submit" name="submit" value="1" data-confirm-click="Send your self-assessment to the head?">Send to the head</button>
        </div>
    <?php elseif ($headEdit): ?>
        <div class="actions" style="display:flex;gap:.5rem">
            <button class="btn" type="submit">Save draft</button>
            <button class="btn btn-primary" type="submit" name="complete" value="1" data-confirm-click="Complete this appraisal? <?= e($t['name']) ?> will be able to read it.">Complete appraisal</button>
        </div>
    <?php endif; ?>
</form>

<?php if ($done): ?>
<div class="card" style="margin-top:1.25rem">
    <h2>Teacher's acknowledgement</h2>
    <?php if ($a['acknowledged_at']): ?>
        <p>Acknowledged on <?= e(date('j M Y', strtotime($a['acknowledged_at']))) ?>.</p>
        <?php if ($a['teacher_response']): ?><p style="white-space:pre-line"><?= e($a['teacher_response']) ?></p><?php endif; ?>
    <?php elseif ($own): ?>
        <form method="post" action="<?= e(url('teachers/' . $t['id'] . '/appraisal/ack')) ?>" class="form">
            <?= csrf_field() ?><input type="hidden" name="term" value="<?= (int) $term['id'] ?>">
            <label>Your response (optional)<textarea name="teacher_response" rows="3" maxlength="2000"></textarea></label>
            <button class="btn btn-primary" type="submit">I have read this appraisal</button>
        </form>
    <?php else: ?>
        <p class="muted">Waiting for <?= e($t['name']) ?> to read and acknowledge it.</p>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>
