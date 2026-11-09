<?php
$subjectsLeft = [];
foreach ($coverage as $cv) {
    $subjectsLeft[$cv['id']] = $cv;
}
$hm = fn($t) => substr((string) $t, 0, 5);
?>
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('timetable')) ?>">Time table</a> /</div>
    <h1><?= e($class['name']) ?></h1></div>
    <div class="actions">
        <a class="btn btn-ghost" href="<?= e(url('timetable/print', ['class' => $class['id']])) ?>" target="_blank" rel="noopener">Print</a>
        <?php if (can('timetable.manage') && !$editing): ?><a class="btn btn-primary" href="<?= e(url('timetable/class/' . $class['id'], ['edit' => 1])) ?>">Edit</a><?php endif; ?>
    </div>
</div>

<?php if ($editing): ?>
<form method="post" action="<?= e(url('timetable/class/' . $class['id'])) ?>">
    <?= csrf_field() ?>
<?php endif; ?>
<div class="card table-wrap" style="padding:.5rem">
    <table class="tt-grid">
        <tr><th>Time</th><?php foreach (Timetable::DAYS as $d => $dn): ?><th><?= e($dn) ?></th><?php endforeach; ?></tr>
        <?php foreach ($periods as $p): ?>
            <?php if ($p['kind'] !== 'lesson'): ?>
                <tr class="tt-fixed tt-<?= e($p['kind']) ?>"><td><strong><?= e($hm($p['start_time'])) ?>–<?= e($hm($p['end_time'])) ?></strong></td><td colspan="5"><?= e($p['label']) ?></td></tr>
                <?php continue; ?>
            <?php endif; ?>
            <tr>
                <td><strong><?= e($p['label']) ?></strong><br><small><?= e($hm($p['start_time'])) ?>–<?= e($hm($p['end_time'])) ?></small></td>
                <?php foreach (Timetable::DAYS as $d => $dn): $s = $grid[$d][$p['id']] ?? null; ?>
                    <td class="<?= $s && $s['class_subject_id'] ? 'tt-lesson' : 'tt-empty' ?>">
                        <?php if ($editing): ?>
                            <select name="cell[<?= $d ?>][<?= (int) $p['id'] ?>]" aria-label="<?= e($dn . ' ' . $p['label']) ?>">
                                <option value="0">—</option>
                                <?php foreach ($subjectsLeft as $csId => $cv): ?><option value="<?= (int) $csId ?>"<?= selected($csId, $s['class_subject_id'] ?? 0) ?>><?= e($cv['name']) ?></option><?php endforeach; ?>
                            </select>
                            <input name="room[<?= $d ?>][<?= (int) $p['id'] ?>]" value="<?= e($s['room'] ?? '') ?>" placeholder="Room" maxlength="40" class="tt-room" aria-label="Room">
                        <?php elseif ($s && $s['class_subject_id']): ?>
                            <strong><?= e($s['subject_name']) ?></strong><br><small><?= e($s['teacher_name'] ?? 'no teacher') ?><?= $s['room'] ? ' · ' . e($s['room']) : '' ?></small>
                        <?php endif; ?>
                    </td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php if ($editing): ?>
    <button class="btn btn-primary" type="submit">Save time table</button>
    <a class="btn btn-ghost" href="<?= e(url('timetable/class/' . $class['id'])) ?>">Cancel</a>
    <span class="hint">Lessons you place by hand are kept when the time table is generated again. A lesson is refused if its teacher is already teaching elsewhere at that time.</span>
</form>
<?php endif; ?>

<h2 style="margin-top:1.5rem">Lessons per week</h2>
<div class="card" style="padding:0">
    <div class="table-wrap"><table>
        <tr><th>Subject</th><th>Teacher</th><th class="num">Planned</th><th class="num">Placed</th><th></th></tr>
        <?php foreach ($coverage as $cv): $diff = (int) $cv['scheduled'] - (int) $cv['planned']; ?>
        <tr>
            <td><?= e($cv['name']) ?></td><td><?= e($cv['teacher_name'] ?? '— none —') ?></td>
            <td class="num"><?= (int) $cv['planned'] ?></td><td class="num"><?= (int) $cv['scheduled'] ?></td>
            <td><?= $diff === 0 ? badge('OK', 'good') : ($diff < 0 ? badge(abs($diff) . ' missing', 'warn') : badge($diff . ' extra', 'info')) ?></td>
        </tr>
        <?php endforeach; ?>
    </table></div>
</div>
