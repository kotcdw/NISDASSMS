<div class="page-head">
    <div><h1>Live classes<?= $prov ? ' — ' . e(Learning::PROVIDERS[$prov]) : '' ?></h1></div>
    <div class="actions"><a class="btn" href="<?= e(url('lms')) ?>">Learning materials</a></div>
</div>
<?php require __DIR__ . '/_provtabs.php'; ?>
<?php foreach ($rows as $s): $start = strtotime($s['starts_at']); $open = $s['status'] === 'scheduled' && time() >= $start - LiveController::EARLY_MIN * 60 && time() <= $start + ((int) $s['duration_min'] + 15) * 60; $past = time() > $start + ((int) $s['duration_min'] + 15) * 60; ?>
<div class="card" style="display:flex;gap:1rem;align-items:center;justify-content:space-between;flex-wrap:wrap<?= $past || $s['status'] === 'cancelled' ? ';opacity:.6' : '' ?>">
    <div style="display:flex;gap:.8rem;align-items:center"><?= Engine::icon($s['provider'] === 'other' ? 'live' : $s['provider'], 44) ?>
        <div><strong><?= e($s['title']) ?></strong> <?= $s['status'] === 'cancelled' ? badge('Cancelled', 'neutral') : ($open ? badge('Live now', 'good') : '') ?><br>
        <small class="muted"><?= e(fmt_date($s['starts_at'], 'l j M')) ?> at <?= e(date('H:i', $start)) ?> · <?= (int) $s['duration_min'] ?> min · <?= e($s['class_name']) ?><?= $s['subject_name'] ? ' · ' . e($s['subject_name']) : '' ?> · <?= e($s['teacher_name'] ?? '') ?></small></div></div>
    <div><?php if ($open): ?><a class="btn btn-primary" href="<?= e(url('live/' . $s['id'] . '/join')) ?>" target="_blank" rel="noopener">Join on <?= e(Learning::PROVIDERS[$s['provider']]) ?></a><?php else: ?><a class="btn btn-ghost" href="<?= e(url('live/' . $s['id'])) ?>">Details</a><?php endif; ?></div>
</div>
<?php endforeach; ?>
<?php if (!$rows): ?><div class="card"><p class="muted">No live classes planned.</p></div><?php endif; ?>
