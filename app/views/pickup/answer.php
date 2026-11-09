<?php /* The parent's answer page, opened from the SMS link (no sign-in). Expects $pu (or null), $child. */ ?>
<div class="pk-answer">
    <?php if (!$pu || !$child): ?>
        <div class="card"><h1>Link not valid</h1><p>This pick-up link is not valid. If someone is at the school to collect your child, please call the school.</p></div>
    <?php else: $first = $child['first_name']; ?>
        <div class="card">
            <p class="pk-school"><?= e(school_name()) ?></p>
            <h1>Pick-up request for <?= e($first) ?></h1>
            <p class="muted"><?= e($child['class_name'] ?? '') ?> · <?= e(date('D j M, g:ia', strtotime($pu['created_at']))) ?></p>
            <div class="pk-person">
                <?php if ($pu['photo'] && $pu['status'] === 'waiting'): ?><img src="<?= e(url('p/' . $pu['token'] . '/photo')) ?>" alt="Photo of <?= e($pu['collector_name']) ?>"><?php endif; ?>
                <div><strong><?= e($pu['collector_name']) ?></strong>
                    <span><?= e($pu['relationship'] ?: 'Relationship not given') ?></span>
                    <span><?= e(Pickups::ID_TYPES[$pu['id_type']] ?? '') ?> <?= e($pu['id_number'] ? '•••' . substr($pu['id_number'], -4) : '') ?></span>
                    <?php if ($pu['phone']): ?><span><?= e($pu['phone']) ?></span><?php endif; ?></div>
            </div>
            <?php if ($pu['status'] === 'waiting'): ?>
                <p><strong>Do you allow this person to take <?= e($first) ?> home now?</strong></p>
                <form method="post" action="<?= e(url('p/' . $pu['token'])) ?>" class="pk-buttons">
                    <?= csrf_field() ?>
                    <button class="btn btn-good btn-lg" name="decision" value="approve" type="submit">Yes, allow</button>
                    <button class="btn btn-danger btn-lg" name="decision" value="refuse" type="submit">No — keep my child at school</button>
                </form>
                <p class="muted pk-small">If you do not know this person, press No and call the school straight away.</p>
            <?php elseif ($pu['status'] === 'approved' || $pu['status'] === 'released'): ?>
                <p class="alert alert-success">You allowed <?= e($pu['collector_name']) ?> to collect <?= e($first) ?>.<?= $pu['status'] === 'released' ? ' ' . e($first) . ' has left the school.' : '' ?></p>
            <?php elseif ($pu['status'] === 'refused'): ?>
                <p class="alert alert-error"><?= e($first) ?> stays at school. Security will not release <?= e($first) ?> to this person.</p>
            <?php else: ?>
                <p class="alert alert-warning">This request is closed (<?= e(strtolower(Pickups::STATUS[$pu['status']][0] ?? $pu['status'])) ?>). <?= e($first) ?> was not released. Please call the school.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
