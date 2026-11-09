<?php $start = strtotime($s['starts_at']); ?>
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('live', ['provider' => $s['provider']])) ?>">Live classes</a> / <?= e($s['class_name']) ?></div>
    <h1 style="display:flex;gap:.6rem;align-items:center"><?= Engine::icon($s['provider'] === 'other' ? 'live' : $s['provider'], 40) ?> <?= e($s['title']) ?></h1>
    <p><?= $s['status'] === 'cancelled' ? badge('Cancelled', 'neutral') : ($open ? badge('Live now', 'good') : '') ?></p></div>
    <div class="actions">
        <?php if ($s['status'] === 'scheduled' && ($open || $manage)): ?><a class="btn btn-primary" href="<?= e(url('live/' . $s['id'] . '/join')) ?>" target="_blank" rel="noopener"><?= $manage ? 'Start / open' : 'Join' ?> on <?= e(Learning::PROVIDERS[$s['provider']]) ?></a><?php endif; ?>
        <?php if ($manage && $s['status'] === 'scheduled'): ?><a class="btn" href="<?= e(url('live/' . $s['id'] . '/edit')) ?>">Change</a>
        <form method="post" action="<?= e(url('live/' . $s['id'] . '/cancel')) ?>" class="inline" data-confirm="Cancel this live class? Students and parents will be told."><?= csrf_field() ?><button class="btn btn-ghost" type="submit">Cancel class</button></form><?php endif; ?>
    </div>
</div>
<div class="cols">
<div class="card">
    <dl class="details">
        <dt>When</dt><dd><?= e(fmt_date($s['starts_at'], 'l j F Y')) ?>, <?= e(date('H:i', $start)) ?> – <?= e(date('H:i', $start + (int) $s['duration_min'] * 60)) ?></dd>
        <dt>Class</dt><dd><?= e($s['class_name']) ?><?= $s['subject_name'] ? ' · ' . e($s['subject_name']) : '' ?></dd>
        <dt>Teacher</dt><dd><?= e($s['teacher_name'] ?? '') ?></dd>
        <dt>App</dt><dd><?= e(Learning::PROVIDERS[$s['provider']]) ?></dd>
        <?php $showCodes = $manage || ($open && $s['status'] === 'scheduled'); // students and parents see them only once the class opens ?>
        <?php if ($s['meeting_code'] && $showCodes): ?><dt>Meeting ID</dt><dd><?= e($s['meeting_code']) ?></dd><?php endif; ?>
        <?php if ($s['passcode'] && $showCodes): ?><dt>Passcode</dt><dd><?= e($s['passcode']) ?></dd><?php endif; ?>
        <?php if (!$showCodes && ($s['meeting_code'] || $s['passcode']) && $s['status'] === 'scheduled' && $start > time()): ?><dt>Meeting ID &amp; passcode</dt><dd class="muted">Shown here when the class opens (<?= LiveController::EARLY_MIN ?> minutes before the start)</dd><?php endif; ?>
        <?php if ($s['notes']): ?><dt>Notes</dt><dd><?= nl2br(e($s['notes'])) ?></dd><?php endif; ?>
    </dl>
    <?php if (!$manage && !$open && $s['status'] === 'scheduled' && $start > time()): ?><p class="muted">The Join button appears <?= LiveController::EARLY_MIN ?> minutes before the start. You will get a reminder.</p><?php endif; ?>
</div>
<div>
    <?php if ($manage): $n = count(array_filter($register, fn($r) => $r['att'])); ?>
    <div class="card"><h2>Who joined</h2><p><strong><?= $n ?></strong> of <?= count($register) ?> students</p>
        <div class="table-wrap" style="max-height:440px;overflow:auto"><table>
            <?php foreach ($register as $r): ?><tr><td><?= e($r['last_name'] . ', ' . $r['first_name']) ?></td><td><?= $r['att'] ? badge(date('H:i', strtotime($r['att']['joined_at'])), 'good') . ($r['att']['by_role'] === 'parent' ? ' <small class="muted">via parent</small>' : '') : '<small class="muted">—</small>' ?></td></tr><?php endforeach; ?>
        </table></div>
    </div>
    <?php endif; ?>
</div>
</div>
