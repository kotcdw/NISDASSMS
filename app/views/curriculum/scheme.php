<?php
$strandOptions = function (?int $sel) use ($tree): string {
    $h = '<option value="">—</option>';
    foreach ($tree as $st) {
        $h .= '<optgroup label="' . e($st['name']) . '">';
        foreach ($st['subs'] ?: [$st] as $sub) {
            $h .= '<option value="' . (int) $sub['id'] . '"' . ((int) $sel === (int) $sub['id'] ? ' selected' : '') . '>' . e($sub['name']) . '</option>';
        }
        $h .= '</optgroup>';
    }
    return $h;
};
$weekOptions = function (int $sel) use ($weeks): string {
    $h = '';
    for ($w = 1; $w <= max($weeks, $sel); $w++) {
        $h .= '<option value="' . $w . '"' . ($w === $sel ? ' selected' : '') . '>Week ' . $w . '</option>';
    }
    return $h;
};
?>
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('curriculum', ['term' => $term['id']])) ?>">Scheme of work</a> /</div>
    <h1><?= e($cs['class_name'] . ' · ' . $cs['subject_name']) ?></h1>
    <p class="muted"><?= e($term['year_name'] . ' · ' . $term['name']) ?> · <?= (int) $weeks ?> weeks<?= $week ? ' · now in week ' . (int) $week : '' ?> · teacher: <?= e($cs['teacher_name'] ?? '—') ?></p></div>
    <div class="actions"><a class="btn btn-ghost" href="<?= e(url('curriculum/scheme/' . $cs['id'] . '/print', ['term' => $term['id']])) ?>" target="_blank" rel="noopener">Print</a></div>
</div>

<?php if (!$items): ?>
    <div class="alert alert-info">No topics planned for this term yet.</div>
    <?php if ($edit): ?>
    <div class="grid-2">
        <form method="post" action="<?= e(url('curriculum/scheme/' . $cs['id'] . '/draft')) ?>" class="card">
            <?= csrf_field() ?><input type="hidden" name="term" value="<?= (int) $term['id'] ?>">
            <h2>Start from the curriculum</h2>
            <button class="btn btn-primary" type="submit">Draft the scheme</button>
        </form>
        <?php if ($others): ?>
        <form method="post" action="<?= e(url('curriculum/scheme/' . $cs['id'] . '/copy')) ?>" class="card form">
            <?= csrf_field() ?><input type="hidden" name="term" value="<?= (int) $term['id'] ?>">
            <h2>Copy another scheme</h2>
            <label>Copy from<select name="source"><?php foreach ($others as $o): ?><option value="<?= (int) $o['term_id'] ?>:<?= (int) $o['class_subject_id'] ?>"><?= e($o['class_name'] . ' · ' . $o['year_name'] . ' ' . $o['term_name'] . ' (' . $o['n'] . ' topics)') ?></option><?php endforeach; ?></select></label>
            <button class="btn" type="submit">Copy topics</button>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
<?php elseif ($edit): ?>
    <?php foreach ($items as $it): ?>
        <form id="tick-<?= (int) $it['id'] ?>" method="post" action="<?= e(url('curriculum/scheme/item/' . $it['id'] . '/tick')) ?>" hidden><?= csrf_field() ?></form>
    <?php endforeach; ?>
    <form method="post" action="<?= e(url('curriculum/scheme/' . $cs['id'] . '/save')) ?>">
        <?= csrf_field() ?><input type="hidden" name="term" value="<?= (int) $term['id'] ?>">
        <div class="card" style="padding:0"><div class="table-wrap"><table class="sow-table">
            <thead><tr><th style="width:110px">Week</th><th>Strand / sub-strand</th><th>Topic</th><th>Indicators / objectives</th><th>Resources</th><th style="width:150px">Taught on</th><th>Remark</th><th>Remove</th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): $id = (int) $it['id']; $late = !$it['taught_on'] && $week && (int) $it['week_no'] < $week; ?>
                <tr class="<?= $it['taught_on'] ? 'sow-done' : ($late ? 'sow-late' : '') ?>">
                    <td><select name="i[<?= $id ?>][week_no]" aria-label="Week"><?= $weekOptions((int) $it['week_no']) ?></select></td>
                    <td><select name="i[<?= $id ?>][strand_id]" aria-label="Strand"><?= $strandOptions($it['strand_id'] !== null ? (int) $it['strand_id'] : null) ?></select></td>
                    <td><input name="i[<?= $id ?>][topic]" value="<?= e($it['topic']) ?>" maxlength="200" aria-label="Topic"></td>
                    <td><input name="i[<?= $id ?>][indicators]" value="<?= e((string) $it['indicators']) ?>" maxlength="255" aria-label="Indicators"></td>
                    <td><input name="i[<?= $id ?>][resources]" value="<?= e((string) $it['resources']) ?>" maxlength="200" aria-label="Resources"></td>
                    <td><input type="date" name="i[<?= $id ?>][taught_on]" value="<?= e((string) $it['taught_on']) ?>" aria-label="Taught on">
                        <button class="btn btn-sm <?= $it['taught_on'] ? 'btn-ghost' : 'btn-good' ?>" type="submit" form="tick-<?= $id ?>" style="margin-top:.3rem"><?= $it['taught_on'] ? 'Untick' : '✓ Taught today' ?></button></td>
                    <td><input name="i[<?= $id ?>][remark]" value="<?= e((string) $it['remark']) ?>" maxlength="200" aria-label="Remark"></td>
                    <td><input type="checkbox" name="delete[]" value="<?= $id ?>" aria-label="Remove"></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div></div>
        <button class="btn btn-primary" type="submit">Save scheme</button>
        <span class="muted" style="font-size:.85rem;margin-left:.6rem">Rows in red are behind schedule; green rows are taught.</span>
    </form>
<?php else: ?>
    <div class="card" style="padding:0"><div class="table-wrap"><table>
        <thead><tr><th>Week</th><th>Strand / sub-strand</th><th>Topic</th><th>Indicators</th><th>Taught</th><th>Remark</th></tr></thead>
        <tbody><?php foreach ($items as $it): ?>
            <tr class="<?= $it['taught_on'] ? 'sow-done' : (($week && (int) $it['week_no'] < $week) ? 'sow-late' : '') ?>"><td><?= (int) $it['week_no'] ?></td><td><?= e(trim(($it['parent_strand_name'] ? $it['parent_strand_name'] . ' › ' : '') . ($it['strand_name'] ?? ''))) ?></td><td><?= e($it['topic']) ?></td><td><?= e((string) $it['indicators']) ?></td><td><?= $it['taught_on'] ? e(date('j M', strtotime($it['taught_on']))) : '—' ?></td><td><?= e((string) $it['remark']) ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div></div>
<?php endif; ?>

<?php if ($edit): ?>
<form method="post" action="<?= e(url('curriculum/scheme/' . $cs['id'] . '/add')) ?>" class="form card" style="margin-top:1.25rem">
    <?= csrf_field() ?><input type="hidden" name="term" value="<?= (int) $term['id'] ?>">
    <h2>Add a topic</h2>
    <div class="grid-3">
        <label class="required">Week<select name="week_no"><?= $weekOptions($week ?: 1) ?></select></label>
        <label>Strand / sub-strand<select name="strand_id"><?= $strandOptions(null) ?></select></label>
        <label class="required">Topic<input name="topic" maxlength="200" required></label>
        <label>Indicators / objectives<input name="indicators" maxlength="255"></label>
        <label>Resources<input name="resources" maxlength="200" placeholder="e.g. counters, textbook p. 24"></label>
    </div>
    <button class="btn" type="submit">Add topic</button>
</form>
<?php endif; ?>
