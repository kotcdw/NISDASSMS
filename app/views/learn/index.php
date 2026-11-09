<?php $preview = !empty($p['preview']); ?>
<?php if ($preview): ?><div class="alert alert-info" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:center"><span><strong>Preview:</strong> the quiz page as <?= e($p['first_name'] . ' ' . $p['last_name']) ?> sees it. Starting quizzes is switched off in preview so no attempts are recorded for the student.</span><a class="btn btn-sm" href="<?= e(url('learn')) ?>">Choose another student</a></div><?php endif; ?>
<div class="page-head"><div><h1>My quizzes</h1></div></div>
<?php foreach ($quizzes as $q): $open = Quiz::isOpen($q); $left = (int) $q['max_attempts'] - (int) $q['used']; ?>
<div class="card" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:center">
    <div style="flex:1 1 260px">
        <h2 style="margin:0"><?= e($q['title']) ?></h2>
        <p class="muted" style="margin:.2rem 0 0"><?= e($q['subject_name']) ?> · <?= (int) $q['questions'] ?> questions<?= (int) $q['time_limit'] ? ' · ' . (int) $q['time_limit'] . ' minutes' : '' ?><?= $q['closes_at'] ? ' · closes ' . e(date('D j M, H:i', strtotime($q['closes_at']))) : '' ?></p>
        <?php if ($q['instructions']): ?><p style="margin:.3rem 0 0;font-size:.9rem"><?= e($q['instructions']) ?></p><?php endif; ?>
    </div>
    <div style="text-align:right">
        <?php if ($q['best'] !== null): ?><p style="margin:0 0 .3rem">Best score: <strong><?= e(Quiz::fmt($q['best'])) ?>/<?= e(Quiz::fmt($q['max_score'])) ?></strong> <a href="<?= e(url($preview ? 'quizzes/' . $q['id'] . '/attempts/' . $q['best_attempt'] : 'learn/attempt/' . $q['best_attempt'] . '/result')) ?>">review</a></p><?php endif; ?>
        <?php if ($q['open_attempt'] && $preview): ?><?= badge('Quiz in progress', 'info') ?>
        <?php elseif ($q['open_attempt']): ?><a class="btn btn-primary" href="<?= e(url('learn/attempt/' . $q['open_attempt'])) ?>">Continue</a>
        <?php elseif ($open && $left > 0 && $preview): ?><?= badge('Open — student can start it', 'good') ?>
        <?php elseif ($open && $left > 0): ?>
            <form method="post" action="<?= e(url('learn/' . $q['id'] . '/start')) ?>" data-confirm="Start the quiz now?<?= (int) $q['time_limit'] ? ' The ' . (int) $q['time_limit'] . '-minute timer starts immediately.' : '' ?>"><?= csrf_field() ?><button class="btn btn-primary" type="submit"><?= (int) $q['used'] ? 'Try again (' . $left . ' left)' : 'Start quiz' ?></button></form>
        <?php elseif ($q['status'] === 'published' && $q['opens_at'] && $q['opens_at'] > date('Y-m-d H:i:s')): ?><?= badge('Opens ' . date('D j M H:i', strtotime($q['opens_at'])), 'warn') ?>
        <?php else: ?><?= badge($left <= 0 ? 'Done' : 'Closed') ?><?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
<?php if (!$quizzes): ?><div class="card"><p class="muted" style="margin:0">No quizzes yet.</p></div><?php endif; ?>
