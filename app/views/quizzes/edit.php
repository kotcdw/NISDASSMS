<?php $id = (int) $q['id']; $total = array_sum(array_map(fn($x) => (float) $x['marks'], $questions)); $open = Quiz::isOpen($q); ?>
<div class="page-head">
    <div><p class="breadcrumb"><a href="<?= e(url('quizzes')) ?>">Quizzes</a></p><h1><?= e($q['title']) ?> <?= $q['status'] === 'draft' ? badge('Draft') : ($q['status'] === 'closed' ? badge('Closed') : ($open ? badge('Open', 'good') : badge('Scheduled', 'warn'))) ?></h1></div>
    <div class="actions">
        <a class="btn" href="<?= e(url('quizzes/' . $id . '/results')) ?>">Results</a>
        <?php foreach (['published' => ['Publish', 'btn-primary'], 'closed' => ['Close now', ''], 'draft' => ['Back to draft', 'btn-ghost']] as $st => [$lbl, $cls]): if ($st === $q['status'] || ($st === 'draft' && $attempts) || ($st === 'closed' && $q['status'] === 'draft')) continue; ?>
            <form method="post" action="<?= e(url('quizzes/' . $id . '/status')) ?>" class="inline"<?= $st === 'closed' ? ' data-confirm="Close the quiz? Unfinished attempts will be marked now."' : '' ?>><?= csrf_field() ?><input type="hidden" name="status" value="<?= $st ?>"><button class="btn <?= $cls ?>" type="submit"><?= $q['status'] === 'closed' && $st === 'published' ? 'Re-open' : $lbl ?></button></form>
        <?php endforeach; ?>
    </div>
</div>
<div class="cols">
    <div>
        <?php foreach ($questions as $n => $qq): ?>
        <div class="quiz-q" id="q<?= (int) $qq['id'] ?>">
            <div style="display:flex;justify-content:space-between;gap:.5rem;align-items:center;margin-bottom:.4rem">
                <strong>Q<?= $n + 1 ?> · <?= e(Quiz::TYPES[$qq['type']]) ?> · <?= e(Quiz::fmt($qq['marks'])) ?> mark(s)</strong>
                <span>
                    <form method="post" action="<?= e(url('quizzes/' . $id . '/questions/' . $qq['id'] . '/move')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="dir" value="up"><button class="btn btn-sm btn-ghost" type="submit" title="Move up">↑</button></form>
                    <form method="post" action="<?= e(url('quizzes/' . $id . '/questions/' . $qq['id'] . '/move')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="dir" value="down"><button class="btn btn-sm btn-ghost" type="submit" title="Move down">↓</button></form>
                    <form method="post" action="<?= e(url('quizzes/' . $id . '/questions/' . $qq['id'] . '/delete')) ?>" class="inline" data-confirm="Delete this question?"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" type="submit" title="Delete">✕</button></form>
                </span>
            </div>
            <details><summary><?= e(mb_strimwidth($qq['prompt'], 0, 140, '…')) ?></summary><div style="margin-top:.6rem"><?php require __DIR__ . '/_question_form.php'; ?></div></details>
        </div>
        <?php endforeach; ?>
        <div class="card" id="new">
            <h2>Add a question</h2>
            <?php $qq = null; require __DIR__ . '/_question_form.php'; ?>
        </div>
    </div>
    <div>
        <div class="card">
            <h2>Settings</h2>
            <form method="post" action="<?= e(url('quizzes/' . $id)) ?>" class="form"><?= csrf_field() ?><?php require __DIR__ . '/_settings.php'; ?><button class="btn" type="submit">Save settings</button></form>
        </div>
        <?php if (!$attempts || !db()->value('SELECT 1 FROM quiz_attempts WHERE quiz_id = ? AND submitted_at IS NOT NULL LIMIT 1', [$id])): ?>
        <form method="post" action="<?= e(url('quizzes/' . $id . '/delete')) ?>" data-confirm="Delete this quiz and its questions?"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">Delete quiz</button></form>
        <?php endif; ?>
    </div>
</div>
