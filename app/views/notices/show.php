<?php [$cl, $ct] = Notices::CATEGORIES[$n['category']]; ?>
<div class="page-head">
    <div><p class="breadcrumb"><a href="<?= e(url('notices')) ?>">Notice board</a></p>
    <h1><?= e($n['title']) ?> <?= badge($cl, $ct) ?><?= ($n['priority'] ?? 'normal') !== 'normal' ? ' ' . badge(ucfirst($n['priority']), $n['priority'] === 'urgent' ? 'bad' : 'warn') : '' ?></h1>
    <?php if (!empty($n['circular_no'])): ?><p class="muted" style="margin:-.3rem 0 .4rem">Circular no. <strong><?= e($n['circular_no']) ?></strong></p><?php endif; ?>
    <p class="muted"><?= e(date('l j F Y', strtotime($n['publish_at']))) ?><?= $n['author'] ? ' · ' . e($n['author']) : '' ?><?= can('notices.manage') ? ' · for ' . e(Notices::label($n['audience'])) : '' ?></p></div>
    <?php if (can('notices.manage')): ?><div class="actions"><a class="btn" href="<?= e(url('notices/' . $n['id'] . '/edit')) ?>">Edit</a>
        <form method="post" action="<?= e(url('notices/' . $n['id'] . '/delete')) ?>" class="inline" data-confirm="Delete this notice?"><?= csrf_field() ?><button class="btn btn-ghost" type="submit">Delete</button></form></div><?php endif; ?>
</div>
<div class="cols">
    <div class="card">
        <div class="notice-body"><?= nl2br(e($n['body'])) ?></div>
        <?php if ($n['attachment']): ?><p style="margin-top:1rem"><a class="btn" href="<?= e(url('notices/' . $n['id'] . '/file')) ?>">⬇ <?= e($n['attachment_name']) ?></a></p><?php endif; ?>
        <?php if ($n['requires_ack'] && !can('notices.manage')): ?>
            <?php if ($mine && $mine['acknowledged_at']): ?><div class="alert alert-success" style="margin-top:1rem">You confirmed you read this on <?= e(date('d M Y H:i', strtotime($mine['acknowledged_at']))) ?>.</div>
            <?php else: ?><form method="post" action="<?= e(url('notices/' . $n['id'] . '/ack')) ?>" style="margin-top:1rem"><?= csrf_field() ?><button class="btn btn-good" type="submit">✓ I have read and understood this</button></form><?php endif; ?>
        <?php endif; ?>
    </div>
    <?php if ($stats): ?>
    <div class="card">
        <h2>Who has seen it</h2>
        <p style="margin:0 0 .3rem"><strong><?= $stats['reads'] ?></strong> opened<?= $n['requires_ack'] ? ' · <strong>' . $stats['acks'] . '</strong> confirmed' : '' ?><?= $stats['audience'] ? ' · ' . $stats['audience'] . ' with a login in the audience · <strong>' . max(0, $stats['audience'] - $stats['reads']) . '</strong> not opened yet' : '' ?> · <?= export_menu(url('notices/' . $n['id'] . '/reads.csv'), 'Export who read it', 'btn btn-sm') ?></p>
        <?php if ($n['sms_sent_at']): ?><p class="hint">SMS alert sent <?= e(date('d M H:i', strtotime($n['sms_sent_at']))) ?>.</p><?php endif; ?>
        <div style="max-height:360px;overflow:auto;margin-top:.5rem">
        <?php foreach ($stats['list'] as $r): ?><p style="margin:0 0 .3rem;font-size:.85rem"><?= e($r['name']) ?> <small class="muted"><?= e(Auth::roleLabel($r['role'])) ?></small> <?= $r['acknowledged_at'] ? badge('Confirmed', 'good') : '' ?></p><?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
