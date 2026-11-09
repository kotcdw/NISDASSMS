<?php [$sl, $st] = LeaveController::STATUS[$l['status']]; $days = (int) ((strtotime($l['to_date']) - strtotime($l['from_date'])) / 86400) + 1; ?>
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('leave')) ?>">Leave</a> /</div>
    <h1><?= e($l['first_name'] . ' ' . $l['last_name']) ?> <?= badge($sl, $st) ?></h1>
    <p class="muted"><?= e($l['class_name'] ?? '') ?> · <?= e(fmt_date($l['from_date'], 'l j M Y')) ?><?= $days > 1 ? ' – ' . e(fmt_date($l['to_date'], 'l j M Y')) . ' (' . $days . ' days)' : '' ?></p></div>
</div>
<div class="cols">
<div class="card">
    <dl class="details" style="grid-template-columns:140px 1fr">
        <dt>Reason</dt><dd><?= e(LeaveController::KINDS[$l['kind']]) ?></dd>
        <dt>Explanation</dt><dd style="white-space:pre-line"><?= e($l['reason']) ?></dd>
        <?php if ($l['attachment']): ?><dt>Document</dt><dd><a href="<?= e(url('leave/' . $l['id'] . '/file')) ?>">📎 <?= e($l['attachment_name']) ?></a></dd><?php endif; ?>
        <dt>Asked by</dt><dd><?= e($l['requested_name'] ?? '—') ?> · <?= e(fmt_date($l['created_at'], 'j M Y H:i')) ?></dd>
        <?php if ($l['decided_by']): ?><dt>Decided by</dt><dd><?= e($l['decided_name'] ?? '') ?> · <?= e(fmt_date($l['decided_at'], 'j M Y H:i')) ?><?= $l['decision_note'] ? '<br>' . e($l['decision_note']) : '' ?></dd><?php endif; ?>
    </dl>
    <?php if ((int) $l['requested_by'] === Auth::id() && ($l['status'] === 'pending' || ($l['status'] === 'approved' && $l['from_date'] > date('Y-m-d')))): ?>
        <form method="post" action="<?= e(url('leave/' . $l['id'] . '/cancel')) ?>" data-confirm="Cancel this leave request?"><?= csrf_field() ?><button class="btn btn-ghost" type="submit">Cancel request</button></form>
    <?php endif; ?>
</div>
<div>
    <?php if ($canDecide && $l['status'] === 'pending'): ?>
    <form method="post" action="<?= e(url('leave/' . $l['id'] . '/decide')) ?>" class="card form">
        <?= csrf_field() ?>
        <h2>Decision</h2>
        <label>Note to the parent (needed if not approved)<textarea name="note" rows="2" maxlength="500"></textarea></label>
        <div class="actions" style="justify-content:flex-start">
            <button class="btn btn-good" type="submit" name="decision" value="approve">Approve</button>
            <button class="btn btn-danger" type="submit" name="decision" value="reject">Not approved</button>
        </div>
    </form>
    <?php endif; ?>
</div>
</div>
