<?php
$q = ['term' => $term['id'], 'week' => $week];
$nextToVet = $vet && $waiting ? db()->value("SELECT id FROM lesson_notes WHERE term_id = ? AND status = 'submitted' ORDER BY submitted_at LIMIT 1", [$term['id']]) : null;
$lateN = count(array_filter($rows, fn($r) => $r['late']));
?>
<?php
$base = array_filter(['term' => $term['id'], 'week' => $week, 'class' => $classId ?: null]);
$stTabs = ['' => ['All', url('lesson-notes', $base)], 'late' => ['Late', url('lesson-notes', $base + ['status' => 'late'])]];
foreach (LessonNotes::STATUS as $k => [$l]) { $stTabs[$k] = [$l, url('lesson-notes', $base + ['status' => $k])]; }
?>
<div class="xgrid-box" data-xgrid="lesson-notes" data-xgrid-file="lesson-notes-week-<?= (int) $week ?>">
<div class="page-head">
    <div><h1>Lesson notes</h1></div>
    <div class="actions">
        <form method="get" action="<?= e(url('lesson-notes')) ?>" class="list-pick ln-pick" data-autosubmit>
            <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
            <?php if (!empty($_GET['term'])): ?><input type="hidden" name="term" value="<?= (int) $term['id'] ?>"><?php endif; ?>
            <?php if ($classId): ?><input type="hidden" name="class" value="<?= (int) $classId ?>"><?php endif; ?>
            <?php if ($week > 1): ?><a class="btn btn-ghost" href="<?= e(url('lesson-notes', ['week' => $week - 1] + $_GET)) ?>" aria-label="Previous week">‹</a><?php endif; ?>
            <select name="week" aria-label="Week"><?php for ($w = 1; $w <= $weeks; $w++): ?><option value="<?= $w ?>"<?= selected($w, $week) ?>>Week <?= $w ?> · <?= e(LessonNotes::weekRange($term, $w)) ?><?= $w === $cur ? ' (this week)' : '' ?></option><?php endfor; ?></select>
            <?php if ($week < $weeks): ?><a class="btn btn-ghost" href="<?= e(url('lesson-notes', ['week' => $week + 1] + $_GET)) ?>" aria-label="Next week">›</a><?php endif; ?>
        </form>
        <?= export_grid_menu() ?>
        <?php if ($nextToVet): ?><a class="btn btn-primary" href="<?= e(url('lesson-notes/' . $nextToVet)) ?>">Vet notes (<?= (int) $waiting ?> waiting)</a><?php endif; ?>
    </div>
</div>
<?= card_tabs($stTabs, (string) $status, 4, 'Notes to show') ?>
    <div class="xg-scroll"><table class="xgrid">
        <thead><tr><?= list_th(['class' => ['Class'], 'subject' => ['Subject']] + ($vet ? ['teacher' => ['Teacher']] : []) + ['topic' => ['Topic'], 'status' => ['Status']]) ?><th class="xg-act"></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): [$sl, $st] = LessonNotes::STATUS[$r['st']]; $n = $r['note']; $mine = LessonNotes::canWrite($r); ?>
        <tr>
            <td data-c="class"><?= e($r['class_name']) ?></td>
            <td data-c="subject"><strong><?= e($r['subject_name']) ?></strong></td>
            <?php if ($vet): ?><td data-c="teacher"><?= e($r['teacher_name'] ?? 'Class teacher') ?></td><?php endif; ?>
            <td data-c="topic" class="audit-details"><?= $n ? e(mb_strimwidth((string) $n['topic'], 0, 70, '…')) : '<span class="muted">—</span>' ?></td>
            <td data-c="status"><?= badge($sl, $st) ?><?= $r['late'] ? '<span hidden> · </span> ' . badge('Late', 'bad') : '' ?></td>
            <td class="xg-act" style="width:auto">
                <?php if ($n && $vet && $r['st'] === 'submitted'): ?><a class="btn btn-sm btn-primary" href="<?= e(url('lesson-notes/' . $n['id'])) ?>">Vet</a>
                <?php elseif ($n && $mine && in_array($r['st'], ['draft', 'returned'], true)): ?><a class="btn btn-sm btn-primary" href="<?= e(url('lesson-notes/' . $n['id'])) ?>"><?= $r['st'] === 'returned' ? 'Correct' : 'Continue' ?></a>
                <?php elseif ($n): ?><a class="btn btn-sm" href="<?= e(url('lesson-notes/' . $n['id'], ['view' => 1])) ?>">Open</a>
                <?php elseif ($mine): ?><a class="btn btn-sm btn-primary" href="<?= e(url('lesson-notes/write', ['cs' => $r['id']] + $q)) ?>">Write</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty><?= $status !== '' ? 'Nothing here.' : 'No subjects assigned to you yet.' ?></p>
    </div>
</div>

<?php if ($vet && $cur !== null && $week <= $cur && $lateN): ?>
<form method="post" action="<?= e(url('lesson-notes/remind')) ?>" class="card form-center remind-bar" data-confirm="Remind the teachers whose week <?= $week ?> notes are not submitted?" data-confirm-ok="Send reminders">
    <?= csrf_field() ?><input type="hidden" name="term" value="<?= (int) $term['id'] ?>"><input type="hidden" name="week" value="<?= $week ?>">
    <span><strong><?= $lateN ?></strong> note<?= $lateN === 1 ? ' is' : 's are' ?> late for week <?= $week ?>.</span>
    <label style="font-weight:400;margin:0"><input type="checkbox" name="sms" value="1"> Also by SMS</label>
    <button class="btn btn-sm" type="submit">Remind teachers</button>
</form>
<?php endif; ?>
