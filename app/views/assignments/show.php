<?php $roll = count($roster); ?>
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('assignments')) ?>">Assignments</a> /</div>
        <h1><?= e($a['title']) ?> <?= badge(...Assignments::STATUS[$a['status']]) ?></h1>
        <p class="muted"><?= e($a['class_name']) ?><?= $a['subject_name'] ? ' · ' . e($a['subject_name']) : '' ?> · set <?= e(fmt_date($a['set_on'], 'D j M')) ?> · due <strong><?= e(fmt_date($a['due_on'], 'D j M Y')) ?></strong><?= $a['max_score'] !== null ? ' · out of ' . e(Results::fmt((float) $a['max_score'])) : '' ?><?= (int) $a['submit_online'] ? ' · hand in online or on paper' : ' · on paper' ?></p>
    </div>
    <div class="actions">
        <a class="btn" href="<?= e(url('assignments/' . $a['id'] . '/edit')) ?>">Edit</a>
        <?php if ($a['status'] === 'draft'): ?>
            <form method="post" action="<?= e(url('assignments/' . $a['id'] . '/status')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="to" value="published"><button class="btn btn-primary" type="submit">Publish</button></form>
        <?php elseif ($a['status'] === 'published'): ?>
            <form method="post" action="<?= e(url('assignments/' . $a['id'] . '/status')) ?>" class="inline" data-confirm="Close this assignment? Students who have not handed in are marked “Not handed in”."><?= csrf_field() ?><input type="hidden" name="to" value="closed"><button class="btn" type="submit">Close</button></form>
        <?php else: ?>
            <form method="post" action="<?= e(url('assignments/' . $a['id'] . '/status')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="to" value="published"><button class="btn" type="submit">Re-open</button></form>
        <?php endif; ?>
        <?php if (!$handed): ?><form method="post" action="<?= e(url('assignments/' . $a['id'] . '/status')) ?>" class="inline" data-confirm="Delete this assignment?"><?= csrf_field() ?><input type="hidden" name="to" value="delete"><button class="btn btn-ghost" type="submit">Delete</button></form><?php endif; ?>
    </div>
</div>
<p class="figs"><span>Handed in <strong><?= (int) $handed ?> / <?= $roll ?></strong></span><span>Marked <strong><?= (int) $nMarked ?></strong></span><span>Average score<?= $a['max_score'] !== null ? ' (out of ' . e(Results::fmt((float) $a['max_score'])) . ')' : '' ?> <strong><?= $avg === null ? '—' : e(Results::fmt($avg)) ?></strong></span><span>Still to hand in <strong><?= max(0, $roll - $handed) ?></strong></span></p>
<?php if ($a['instructions'] || $a['attachment']): ?>
<div class="card"><h2>Instructions</h2><?= $a['instructions'] ? '<p style="white-space:pre-line">' . e($a['instructions']) . '</p>' : '' ?><?php if ($a['attachment']): ?><p><a href="<?= e(url('assignments/' . $a['id'] . '/file')) ?>">📎 <?= e($a['attachment_name']) ?></a></p><?php endif; ?></div>
<?php endif; ?>
<div class="card" style="padding:0">
    <h2 style="padding:1rem 1rem 0">Students</h2>
    <div class="table-wrap"><table>
        <tr><th>Student</th><th>Status</th><th>Work</th><th>Score &amp; feedback</th></tr>
        <?php foreach ($roster as $p): $s = $subs[(int) $p['id']] ?? null; ?>
        <tr id="p<?= (int) $p['id'] ?>">
            <td><strong><?= e($p['last_name'] . ', ' . $p['first_name']) ?></strong><br><small class="muted"><?= e($p['admission_no']) ?></small></td>
            <td><?= $s ? badge(...Assignments::SUB_STATUS[$s['status']]) : badge($a['due_on'] < date('Y-m-d') ? 'Not handed in' : 'Not yet', $a['due_on'] < date('Y-m-d') ? 'bad' : 'neutral') ?><?= $s && $s['submitted_at'] ? '<br><small class="muted">' . e(fmt_date($s['submitted_at'], 'j M H:i')) . ((int) $s['on_paper'] ? ' · on paper' : '') . '</small>' : '' ?></td>
            <td style="max-width:320px"><?php if ($s && $s['body']): ?><div style="white-space:pre-line;font-size:.9rem;max-height:7.5em;overflow:auto"><?= e($s['body']) ?></div><?php endif; ?><?php if ($s && $s['file']): ?><a href="<?= e(url('assignments/' . $a['id'] . '/work/' . $s['id'])) ?>">📎 <?= e($s['file_name']) ?></a><?php endif; ?></td>
            <td>
                <form method="post" action="<?= e(url('assignments/' . $a['id'] . '/mark/' . $p['id'])) ?>" class="mark-form" style="display:flex;gap:.35rem;flex-wrap:wrap;align-items:center">
                    <?= csrf_field() ?>
                    <input name="score" inputmode="decimal" style="width:5rem" placeholder="Score" value="<?= e($s && $s['score'] !== null ? Results::fmt((float) $s['score']) : '') ?>" aria-label="Score for <?= e($p['first_name']) ?>">
                    <input name="feedback" maxlength="1000" style="flex:1 1 160px" placeholder="Feedback (optional)" value="<?= e($s['feedback'] ?? '') ?>" aria-label="Feedback for <?= e($p['first_name']) ?>">
                    <button class="btn btn-sm" type="submit" title="Save the mark (or record that it was handed in on paper)"><?= $s && $s['status'] !== 'missing' ? 'Save' : 'Handed in / save' ?></button>
                    <button class="btn btn-sm btn-primary" type="submit" name="return" value="1" title="Save and show the mark to the student and parent">Return</button>
                    <?php if (!$s || $s['status'] !== 'missing'): ?><button class="btn btn-sm btn-ghost" type="submit" name="missing" value="1" title="Record as not handed in">Not in</button><?php endif; ?>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$roster): ?><tr><td colspan="4" class="muted">No students in this class this year.</td></tr><?php endif; ?>
    </table></div>
</div>
