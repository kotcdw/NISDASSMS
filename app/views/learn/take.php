<div class="page-head"><div><h1><?= e($q['title']) ?></h1><p class="muted"><?= e($q['subject_name']) ?> · attempt <?= (int) $a['attempt_no'] ?> of <?= (int) $q['max_attempts'] ?></p></div></div>
<?php if ($a['deadline_at']): ?><div class="quiz-timer" data-quiz-deadline="<?= strtotime($a['deadline_at']) * 1000 ?>" data-now="<?= time() * 1000 ?>">Time left: <span>--:--</span></div><?php endif; ?>
<?php if ($q['instructions']): ?><div class="alert alert-info"><?= e($q['instructions']) ?></div><?php endif; ?>
<form method="post" action="<?= e(url('learn/attempt/' . $a['id'] . '/submit')) ?>" id="quiz-form" data-autosave="<?= e(url('learn/attempt/' . $a['id'] . '/save')) ?>">
    <?= csrf_field() ?>
    <?php foreach ($questions as $n => $qq): $given = $answers[$qq['id']] ?? ''; ?>
    <div class="quiz-q">
        <p style="margin:0 0 .5rem"><strong>Q<?= $n + 1 ?>.</strong> <?= nl2br(e($qq['prompt'])) ?> <small class="muted">(<?= e(Quiz::fmt($qq['marks'])) ?> mark<?= (float) $qq['marks'] == 1.0 ? '' : 's' ?>)</small></p>
        <div class="opts">
        <?php if ($qq['type'] === 'mcq'): foreach (Quiz::options($qq) as $i => $o): ?>
            <label><input type="radio" name="answer[<?= (int) $qq['id'] ?>]" value="<?= $i ?>"<?= (string) $given === (string) $i ? ' checked' : '' ?>> <?= chr(65 + $i) ?>. <?= e($o) ?></label>
        <?php endforeach; elseif ($qq['type'] === 'truefalse'): ?>
            <label><input type="radio" name="answer[<?= (int) $qq['id'] ?>]" value="true"<?= $given === 'true' ? ' checked' : '' ?>> True</label>
            <label><input type="radio" name="answer[<?= (int) $qq['id'] ?>]" value="false"<?= $given === 'false' ? ' checked' : '' ?>> False</label>
        <?php else: ?>
            <input name="answer[<?= (int) $qq['id'] ?>]" value="<?= e($given) ?>" maxlength="200" autocomplete="off" placeholder="Type your answer">
        <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <button class="btn btn-primary" type="submit" data-confirm-click="Submit your answers? You cannot change them afterwards.">Submit answers</button>
    <span class="hint" data-autosave-status></span>
</form>
