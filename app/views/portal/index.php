<?php if (!empty($preview)): ?><div class="alert alert-info" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:center"><span><strong>Preview:</strong> this is the parent portal exactly as <strong><?= e($preview['name']) ?></strong> (<?= e($preview['phone']) ?>) sees it.</span><a class="btn btn-sm" href="<?= e(url('portal')) ?>">Choose another parent</a></div><?php endif; ?>
<div class="page-head">
    <div><h1>Welcome, <?= e(!empty($preview) ? $preview['name'] : explode(' ', trim(preg_replace('/^(Mr|Mrs|Ms|Miss|Dr|Rev|Pastor|Elder|Madam)\.?\s+/i', '', auth()['name'])))[0]) ?></h1></div>
    <div class="actions"><a class="btn" href="<?= e(url('conversations')) ?>">Messages<?= $unreadMsgs ? ' (' . $unreadMsgs . ' new)' : '' ?></a><?php if (can('shop.order')): ?><a class="btn" href="<?= e(url('shop')) ?>">School shop</a><?php endif; ?><a class="btn btn-primary" href="<?= e(url('conversations/create')) ?>">Write to the school</a></div>
</div>
<div class="cols">
    <div>
        <?php foreach ($children as $child): ?>
        <div class="card child-card">
            <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;align-items:baseline">
                <h2><a href="<?= e(url('portal/child/' . $child['id'])) ?>"><?= e(full_name($child)) ?></a></h2>
                <span class="muted"><?= e($child['class_name'] ?? 'Not enrolled this year') ?><?= $child['class_teacher'] ? ' · Class teacher: ' . e($child['class_teacher']) : '' ?></span>
            </div>
            <?php if ($child['status'] !== 'active'): ?><p class="muted"><?= e(ucfirst($child['status'])) ?></p><?php endif; ?>
            <?php require __DIR__ . '/_child_stats.php'; ?>
            <p style="margin:.8rem 0 0"><a class="btn btn-sm" href="<?= e(url('portal/child/' . $child['id'])) ?>">Attendance, fees, report cards &amp; time table →</a></p>
        </div>
        <?php endforeach; ?>
        <?php if (!$children): ?><div class="card"><p class="muted" style="margin:0">No children are linked to your account yet. Please contact the school office.</p></div><?php endif; ?>
    </div>
    <div>
        <?php if (module_on('notices')): ?>
        <div class="card">
            <h2>Notice board</h2>
            <?php foreach ($notices as $n): ?>
                <p style="margin:0 0 .6rem"><a href="<?= e(url('notices/' . $n['id'])) ?>"<?= $n['read_at'] ? '' : ' style="font-weight:700"' ?>><?= e($n['title']) ?></a><br><small class="muted"><?= e(date('D j M', strtotime($n['publish_at']))) ?><?= $n['requires_ack'] && !$n['acknowledged_at'] ? ' · please confirm you read it' : '' ?></small></p>
            <?php endforeach; ?>
            <?php if (!$notices): ?><p class="muted">No notices.</p><?php endif; ?>
            <a href="<?= e(url('notices')) ?>">All notices →</a>
        </div>
        <?php endif; ?>
        <?php if (module_on('calendar')): ?>
        <div class="card">
            <h2>Coming up</h2>
            <?php foreach ($events as $ev): ?><p style="margin:0 0 .5rem"><strong><?= e(date('D j M', strtotime($ev['start_date']))) ?></strong> — <?= e($ev['title']) ?></p><?php endforeach; ?>
            <?php if (!$events): ?><p class="muted">Nothing scheduled.</p><?php endif; ?>
            <a href="<?= e(url('calendar')) ?>">School calendar →</a>
        </div>
        <?php endif; ?>
    </div>
</div>
