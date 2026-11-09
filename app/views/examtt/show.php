<?php
$hm = fn($t) => substr((string) $t, 0, 5);
$byDay = [];
foreach ($papers as $p) {
    $byDay[$p['exam_date']][] = $p;
}
$badCount = count(array_filter($clashes, fn($c) => $c[0] === 'bad'));
$flagged = [];
foreach ($clashes as $c) {
    $flagged[$c[2]] = $c[0] === 'bad' ? 'bad' : ($flagged[$c[2]] ?? $c[0]);
}
?>
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('exam-timetable')) ?>">Exam timetable</a> /</div>
    <h1><?= e($s['name']) ?></h1>
    </div>
    <div class="actions">
        <?php if ($manage): ?><a class="btn btn-primary" href="#add-paper">+ Add a paper</a><?php endif; ?>
        <a class="btn btn-ghost" href="<?= e(url('exam-timetable/' . $s['id'] . '/print', ['by' => 'class'])) ?>" target="_blank" rel="noopener">Print by class</a>
        <?php if ($manage): ?><a class="btn btn-ghost" href="<?= e(url('exam-timetable/' . $s['id'] . '/print', ['by' => 'invigilator'])) ?>" target="_blank" rel="noopener">Print invigilation duties</a><?php endif; ?>
        <a class="btn btn-ghost" href="<?= e(url('exam-timetable/' . $s['id'] . '/print', ['by' => 'all'])) ?>" target="_blank" rel="noopener">Print full list</a>
    </div>
</div>

<?php if ($s['instructions']): ?><div class="alert alert-info" style="white-space:pre-line"><?= e($s['instructions']) ?></div><?php endif; ?>

<?php if ($manage && $clashes): ?>
<div class="card">
    <h2>Checks <small class="muted"><?= count($clashes) ?> item(s)</small></h2>
    <ul style="margin:0;padding-left:1.1rem">
        <?php foreach ($clashes as [$tone, $msg]): ?><li style="margin-bottom:.25rem"><?= badge($tone === 'bad' ? 'Clash' : ($tone === 'warn' ? 'Check' : 'Note'), $tone) ?> <?= e($msg) ?></li><?php endforeach; ?>
    </ul>
</div>
<?php elseif ($manage && $papers): ?>
    <div class="alert alert-success">No clashes.</div>
<?php endif; ?>


<?php if (!$papers): ?>
    <div class="alert alert-info">No papers yet.</div>
<?php endif; ?>
<?php foreach ($byDay as $day => $list): ?>
<div class="card" style="padding:0">
    <h2 style="padding:1rem 1.25rem 0;margin:0"><?= e(date('l j F', strtotime($day))) ?></h2>
    <div class="table-wrap"><table>
        <thead><tr><th>Time</th><th>Class</th><th>Paper</th><th>Room</th><th>Invigilator</th><?= $manage ? '<th></th>' : '' ?></tr></thead>
        <tbody><?php foreach ($list as $p): $fl = $flagged[(int) $p['id']] ?? null; ?>
            <tr<?= $fl === 'bad' ? ' style="background:var(--bad-soft)"' : '' ?>>
                <td style="white-space:nowrap"><?= e($hm($p['start_time'])) ?>–<?= e($hm($p['end_time'])) ?> <small class="muted"><?= (int) $p['duration_min'] ?> min</small></td>
                <td><?= e($p['class_name']) ?></td>
                <td><strong><?= e($p['subject_name']) ?></strong><?= $p['paper'] ? ' ' . e($p['paper']) : '' ?><?= $p['notes'] ? '<br><small class="muted">' . e($p['notes']) . '</small>' : '' ?></td>
                <td><?= e($p['room_name'] ?? '—') ?></td>
                <td><?= e($p['invigilator_name'] ?? '—') ?></td>
                <?php if ($manage): ?><td style="white-space:nowrap"><a class="btn btn-sm btn-ghost" href="<?= e(url('exam-timetable/papers/' . $p['id'] . '/edit')) ?>">Edit</a>
                    <form method="post" action="<?= e(url('exam-timetable/papers/' . $p['id'] . '/delete')) ?>" class="inline" data-confirm="Remove this paper?"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" type="submit" aria-label="Remove">✕</button></form></td><?php endif; ?>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
</div>
<?php endforeach; ?>

<?php if ($manage): ?>
<form method="post" action="<?= e(url('exam-timetable/' . $s['id'] . '/papers')) ?>" class="form card form-center" id="add-paper">
    <?= csrf_field() ?>
    <h2>Add a paper</h2>
    <div class="grid-2">
        <label class="required">Who sits it<select name="target" required>
            <option value="">Choose…</option>
            <optgroup label="Whole level (one paper per class)"><?php foreach ($levels as $l): ?><option value="level:<?= (int) $l['id'] ?>"<?= selected('level:' . $l['id'], old('target')) ?>><?= e($l['name']) ?> — all classes</option><?php endforeach; ?></optgroup>
            <optgroup label="One class"><?php foreach ($classes as $c): ?><option value="class:<?= (int) $c['id'] ?>"<?= selected('class:' . $c['id'], old('target', $f['class'] ? 'class:' . $f['class'] : '')) ?>><?= e($c['name']) ?></option><?php endforeach; ?></optgroup>
        </select></label>
        <label class="required">Subject<select name="subject_id" required><option value="">Choose…</option><?php foreach ($subjects as $sb): ?><option value="<?= (int) $sb['id'] ?>"<?= selected($sb['id'], old('subject_id')) ?>><?= e($sb['name']) ?></option><?php endforeach; ?></select></label>
        <label>Paper<input name="paper" maxlength="40" value="<?= e(old('paper')) ?>" placeholder="e.g. Paper 1 (objectives)"></label>
        <label class="required">Date<input type="date" name="exam_date" value="<?= e(old('exam_date', $s['start_date'])) ?>" min="<?= e($s['start_date']) ?>" max="<?= e($s['end_date']) ?>" required></label>
        <label class="required">Start time<input type="time" name="start_time" value="<?= e(old('start_time', '08:00')) ?>" required></label>
        <label class="required">Duration (minutes)<input type="number" name="duration_min" min="10" max="300" value="<?= e(old('duration_min', '60')) ?>" required></label>
        <label>Room<select name="room_id"><option value="">The class's own room</option><?php foreach ($rooms as $r): ?><option value="<?= (int) $r['id'] ?>"<?= selected($r['id'], old('room_id')) ?>><?= e($r['name']) ?> (<?= (int) $r['capacity'] ?>)</option><?php endforeach; ?></select></label>
        <label>Invigilator<select name="invigilator_id"><option value="">— later —</option><?php foreach ($staff as $u): ?><option value="<?= (int) $u['id'] ?>"<?= selected($u['id'], old('invigilator_id')) ?>><?= e($u['name']) ?></option><?php endforeach; ?></select></label>
    </div>
    <label>Notes<input name="notes" maxlength="200" value="<?= e(old('notes')) ?>" placeholder="e.g. Bring a mathematical set"></label>
    <button class="btn btn-primary" type="submit">Add paper</button>
</form>

<div class="grid-2 form-center">
    <div class="card">
        <h2>Publish</h2>
        <?php if ($s['status'] === 'published'): ?>
            <p>Published on <?= e(date('j M Y H:i', strtotime($s['published_at']))) ?> and pinned on the notice board.<?= $s['notice_id'] ? ' <a href="' . e(url('notices/' . $s['notice_id'])) . '">View notice</a>' : '' ?></p>
            <form method="post" action="<?= e(url('exam-timetable/' . $s['id'] . '/publish')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="ignore_clashes" value="1"><button class="btn" type="submit">Update the notice</button></form>
            <form method="post" action="<?= e(url('exam-timetable/' . $s['id'] . '/unpublish')) ?>" class="inline" data-confirm="Withdraw the timetable and remove it from the notice board?"><?= csrf_field() ?><button class="btn btn-ghost" type="submit">Withdraw</button></form>
        <?php else: ?>
            <form method="post" action="<?= e(url('exam-timetable/' . $s['id'] . '/publish')) ?>" class="form">
                <?= csrf_field() ?>
                <?php if ($badCount): ?><label><input type="checkbox" name="ignore_clashes" value="1"> Publish anyway (<?= $badCount ?> clash<?= $badCount > 1 ? 'es' : '' ?> above)</label><?php endif; ?>
                <button class="btn btn-primary" type="submit"<?= $papers ? '' : ' disabled' ?>>Publish timetable</button>
            </form>
            <form method="post" action="<?= e(url('exam-timetable/' . $s['id'] . '/delete')) ?>" data-confirm="Delete this exam timetable and all its papers?" style="margin-top:.6rem"><?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit">Delete</button></form>
        <?php endif; ?>
    </div>
    <div class="card">
        <h2>Exam seating</h2>
        <?php foreach ($plans as $pl): ?><p style="margin:0 0 .3rem"><a href="<?= e(url('seating/' . $pl['id'])) ?>"><?= e($pl['title']) ?></a> <small class="muted"><?= (int) $pl['seated'] ?> seated</small></p><?php endforeach; ?>
        <?php if (!$plans): ?><?php endif; ?>
        <a class="btn btn-sm" href="<?= e(url('seating/exam/create', ['series' => $s['id']])) ?>">New exam seating plan</a>
    </div>
</div>

<details class="sum card form-center"><summary>Edit name, dates and instructions</summary>
<form method="post" action="<?= e(url('exam-timetable/' . $s['id'])) ?>" class="form" style="margin-top:.8rem">
    <?= csrf_field() ?><input type="hidden" name="term_id" value="<?= (int) $s['term_id'] ?>">
    <div class="grid-3">
        <label class="required">Name<input name="name" maxlength="120" value="<?= e($s['name']) ?>" required></label>
        <label class="required">First exam day<input type="date" name="start_date" value="<?= e($s['start_date']) ?>" required></label>
        <label class="required">Last exam day<input type="date" name="end_date" value="<?= e($s['end_date']) ?>" required></label>
    </div>
    <label>Instructions<textarea name="instructions" rows="3" maxlength="3000"><?= e((string) $s['instructions']) ?></textarea></label>
    <button class="btn" type="submit">Save</button>
</form>
</details>
<?php endif; ?>
