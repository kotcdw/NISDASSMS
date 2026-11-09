<?php $pct = (float) $a['max_score'] > 0 ? round($a['score'] * 100 / $a['max_score']) : 0; ?>
<div class="page-head"><div><p class="breadcrumb"><a href="<?= e(url('learn')) ?>">My quizzes</a></p><h1><?= e($q['title']) ?></h1></div></div>
<div class="card" style="text-align:center">
    <p class="muted" style="margin:0">Your score</p>
    <p style="font-size:2.4rem;font-weight:800;margin:.2rem 0;color:<?= $pct >= 50 ? 'var(--good)' : 'var(--bad)' ?>"><?= e(Quiz::fmt($a['score'])) ?> / <?= e(Quiz::fmt($a['max_score'])) ?></p>
    <p style="margin:0"><?= $pct ?>% · <?= $pct >= 80 ? 'Excellent work!' : ($pct >= 50 ? 'Well done.' : 'Keep practising — ask your teacher about the questions you missed.') ?></p>
</div>
<?php if ($reveal): ?><h2>Your answers</h2><?php require __DIR__ . '/_review.php'; ?>
<?php elseif ($q['show_answers'] === 'after_close'): ?><p class="muted">The correct answers will be shown here after the quiz closes<?= $q['closes_at'] ? ' (' . e(date('D j M H:i', strtotime($q['closes_at']))) . ')' : '' ?>.</p><?php endif; ?>
