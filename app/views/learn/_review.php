<?php foreach ($questions as $n => $qq): $given = $answers[$qq['id']] ?? null; $ok = Quiz::isCorrect($qq, $given); $opts = Quiz::options($qq); ?>
<div class="quiz-q <?= $ok ? 'right' : 'wrong' ?>">
    <p style="margin:0 0 .4rem"><strong>Q<?= $n + 1 ?>.</strong> <?= nl2br(e($qq['prompt'])) ?> <small class="muted">(<?= e(Quiz::fmt($qq['marks'])) ?>)</small> <?= $ok ? badge('Correct', 'good') : badge('Not correct', 'bad') ?></p>
    <?php
    $show = fn($v) => $qq['type'] === 'mcq' ? ($opts[(int) $v] ?? '') : ($qq['type'] === 'truefalse' ? ucfirst((string) $v) : (string) $v);
    ?>
    <p style="margin:0;font-size:.9rem">Answer given: <strong><?= $given === null || $given === '' ? '<em>none</em>' : e($show($given)) ?></strong></p>
    <?php if ($reveal && !$ok): ?><p style="margin:.2rem 0 0;font-size:.9rem;color:var(--good)">Correct answer: <strong><?= e($qq['type'] === 'short' ? str_replace('|', ' / ', $qq['answer']) : $show($qq['answer'])) ?></strong></p><?php endif; ?>
</div>
<?php endforeach; ?>
