<?php
/* One pick-up: status screen for Security (checks for the parent's answer every few seconds). Expects $pu, $child. */
[$label, $tone] = Pickups::STATUS[$pu['status']] ?? [$pu['status'], ''];
$big = ['waiting' => ['Waiting for the parent', 'Keep ' . $child['first_name'] . ' inside the school. This page updates by itself.'],
    'approved' => ['Approved — you may release ' . $child['first_name'], 'Check the face matches the photo, then press Release.'],
    'refused' => ['Refused — do NOT release ' . $child['first_name'], 'The parent said no. Keep the child inside and call the Head teacher.'],
    'released' => [$child['first_name'] . ' has left', 'Released ' . ($pu['released_at'] ? date('g:ia', strtotime($pu['released_at'])) : '') . '. The parent was told by SMS.'],
    'expired' => ['No answer from the parent', 'Do not release. Ask the office to phone the parent, or record the pick-up again.'],
    'cancelled' => ['Cancelled', 'The child stayed in school.']][$pu['status']] ?? [$label, ''];
?>
<div class="page-head"><div><h1>Pick-up <?= e($pu['ref_no']) ?></h1><p class="page-sub"><?= e($child['first_name'] . ' ' . $child['last_name']) ?> · <?= e($child['class_name'] ?? '') ?> · recorded <?= e(date('D j M, g:ia', strtotime($pu['created_at']))) ?></p></div>
    <div class="actions"><a class="btn btn-ghost" href="<?= e(url('gate')) ?>">Back to the gate</a></div></div>

<section class="gate-status is-<?= e($pu['status']) ?>"<?= $pu['status'] === 'waiting' ? ' data-gate-poll="' . e(url('gate/' . $pu['id'] . '/status')) . '"' : '' ?>>
    <strong><?= e($big[0]) ?></strong><span><?= e($big[1]) ?></span>
    <?php if ($pu['approval_via']): ?><small>Answer: <?= e(Pickups::VIA[$pu['approval_via']] ?? $pu['approval_via']) ?><?= $pu['decided_note'] ? ' — ' . e($pu['decided_note']) : '' ?><?= $pu['decided_at'] ? ' · ' . e(date('g:ia', strtotime($pu['decided_at']))) : '' ?></small><?php endif; ?>
    <div class="gate-status-actions">
        <?php if ($pu['status'] === 'approved' && can('pickup.gate')): ?>
            <form method="post" action="<?= e(url('gate/' . $pu['id'] . '/release')) ?>"><?= csrf_field() ?><button class="btn btn-good btn-lg" type="submit">Release <?= e($child['first_name']) ?></button></form>
        <?php endif; ?>
        <?php if ($pu['status'] === 'waiting' && can('pickup.gate')): ?>
            <form method="post" action="<?= e(url('gate/' . $pu['id'] . '/resend')) ?>"><?= csrf_field() ?><button class="btn" type="submit">Send the SMS again</button></form>
        <?php endif; ?>
        <?php if (in_array($pu['status'], ['waiting', 'approved'], true) && can('pickup.gate')): ?>
            <form method="post" action="<?= e(url('gate/' . $pu['id'] . '/cancel')) ?>" data-confirm="Cancel this pick-up? The child stays in school."><?= csrf_field() ?><button class="btn btn-ghost" type="submit">Cancel</button></form>
        <?php endif; ?>
    </div>
</section>

<?php if ($pu['status'] === 'waiting' && can('pickup.manage')): ?>
<form method="post" action="<?= e(url('gate/' . $pu['id'] . '/decide')) ?>" class="card form gate-officebox">
    <?= csrf_field() ?>
    <h2>Office: answer after phoning the parent</h2>
    <label class="required">Who you spoke to, and on which number<input name="note" required maxlength="200" placeholder="e.g. Mrs Asante on 024 000 0000 (number on record)"></label>
    <div class="gate-actions"><button class="btn btn-good" name="decision" value="approve" type="submit">Parent approves</button><button class="btn btn-danger" name="decision" value="refuse" type="submit">Parent refuses</button></div>
</form>
<?php endif; ?>

<div class="gate-grid">
    <section class="card">
        <h2>Collected by</h2>
        <div class="gate-who">
            <?php if ($pu['photo']): ?><img src="<?= e(url('gate/' . $pu['id'] . '/photo')) ?>" alt="Photo of <?= e($pu['collector_name']) ?>"><?php else: ?><span class="gate-init lg"><?= e(mb_substr($pu['collector_name'], 0, 1)) ?></span><?php endif; ?>
            <div><h3><?= e($pu['collector_name']) ?></h3><p class="muted"><?= e(Pickups::KINDS[$pu['kind']] ?? '') ?><?= $pu['relationship'] ? ' · ' . e($pu['relationship']) : '' ?></p></div>
        </div>
        <dl class="gate-dl">
            <dt>ID card</dt><dd><?= e(Pickups::ID_TYPES[$pu['id_type']] ?? $pu['id_type']) ?> · <?= e($pu['id_number']) ?></dd>
            <?php if ($pu['phone']): ?><dt>Phone</dt><dd><?= e($pu['phone']) ?></dd><?php endif; ?>
            <dt>Status</dt><dd><?= badge($label, $tone ?: 'neutral') ?></dd>
        </dl>
    </section>
    <section class="card">
        <h2>Signature</h2>
        <?php if ($pu['signature']): ?><img class="gate-sig" src="<?= e(url('gate/' . $pu['id'] . '/signature')) ?>" alt="Signature of <?= e($pu['collector_name']) ?>"><?php endif; ?>
    </section>
</div>
