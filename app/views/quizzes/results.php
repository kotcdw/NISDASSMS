<?php $id = (int) $q['id']; ?>
<div class="xgrid-box" data-xgrid="quiz-results" data-xgrid-file="quiz-<?= $id ?>-results">
<div class="page-head">
    <div><p class="breadcrumb"><a href="<?= e(url('quizzes')) ?>">Quizzes</a> › <a href="<?= e(url('quizzes/' . $id)) ?>"><?= e($q['title']) ?></a></p><h1>Results</h1></div>
    <div class="actions"><?= export_grid_menu() ?></div>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['adm' => ['Admission no.'], 'student' => ['Student'], 'score' => ['Score', 'num', 'num'], 'max' => ['Out of', 'num', 'num'], 'pct' => ['%', 'num', 'num'], 'attempts' => ['Attempts', 'num', 'num'], 'at' => ['Submitted', 'date']]) ?></tr></thead>
    <tbody>
    <?php foreach ($pupils as $p): $b = $best[(int) $p['id']] ?? []; $pct = isset($b['score']) && (float) $b['max'] > 0 ? round($b['score'] * 100 / $b['max']) : null; ?>
    <tr>
        <td data-c="adm"><?= e($p['admission_no']) ?></td>
        <td data-c="student"><?= e(full_name($p)) ?><?= !$p['user_id'] ? '<span hidden> · </span> <small class="muted">(no login)</small>' : '' ?></td>
        <td data-c="score" class="num" data-s="<?= isset($b['score']) ? (float) $b['score'] : '' ?>"><?php if (isset($b['score'])): ?><a href="<?= e(url('quizzes/' . $id . '/attempts/' . $b['attempt_id'])) ?>"><?= e(Quiz::fmt($b['score'])) ?></a><?php elseif (!empty($b['in_progress'])): ?><?= badge('In progress', 'warn') ?><?php else: ?>—<?php endif; ?></td>
        <td data-c="max" class="num"><?= isset($b['max']) ? e(Quiz::fmt($b['max'])) : '' ?></td>
        <td data-c="pct" class="num" data-s="<?= $pct ?? '' ?>"><?= $pct !== null ? '<span style="color:' . ($pct >= 50 ? 'var(--good)' : 'var(--bad)') . '">' . $pct . '%</span>' : '' ?></td>
        <td data-c="attempts" class="num"><?= (int) ($b['attempts'] ?? 0) ?></td>
        <td data-c="at" data-s="<?= e((string) ($b['at'] ?? '')) ?>"><?= isset($b['at']) ? e(date('d M H:i', strtotime($b['at']))) : '' ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $pupils ? ' hidden' : '' ?> data-xg-empty>No students in this class yet.</p>
</div>
</div>
<div class="cols" style="margin-top:1.5rem">
    <div class="card">
        <h2>Question analysis</h2>
        <?php foreach ($questions as $n => $qq): $pc = $taken ? round(($right[$qq['id']] ?? 0) * 100 / $taken) : 0; ?>
        <div style="margin-bottom:.55rem"><div style="display:flex;justify-content:space-between;font-size:.85rem"><span>Q<?= $n + 1 ?>. <?= e(mb_strimwidth($qq['prompt'], 0, 50, '…')) ?></span><strong><?= $pc ?>%</strong></div><div class="meter<?= $pc < 50 ? ' low' : ($pc < 75 ? ' mid' : '') ?>"><span style="width:<?= $pc ?>%"></span></div></div>
        <?php endforeach; ?>
    </div>
    <div class="card">
        <h2>Copy to score sheet</h2>
        <?php if (!$assessments): ?><p class="muted">No class assessment exists for <?= e($q['subject_name']) ?> this term. Add one in Exams → the score sheet, then come back.</p>
        <?php elseif ($sheetLocked): ?><p class="muted">The score sheet is submitted or approved, so it is locked.</p>
        <?php else: ?>
        <form method="post" action="<?= e(url('quizzes/' . $id . '/to-assessment')) ?>" class="form" data-confirm="Copy the best quiz scores into this assessment? Existing marks for these students will be replaced.">
            <?= csrf_field() ?>
            <label>Class assessment<select name="assessment_id"><?php foreach ($assessments as $as): ?><option value="<?= (int) $as['id'] ?>"><?= e($as['title']) ?> (out of <?= e(Quiz::fmt($as['max_mark'])) ?>)</option><?php endforeach; ?></select></label>
            <button class="btn" type="submit">Copy scores</button>
        </form>
        <?php endif; ?>
    </div>
</div>
