<?php /** $key, $label, $detail, $link, $ticks */ $t = $ticks[$key] ?? null; $done = $t && $t['done_at']; ?>
<div class="golive-item<?= $done ? ' done' : '' ?>" id="<?= e(str_replace(':', '-', $key)) ?>">
    <form method="post" action="<?= e(url('golive/tick')) ?>" class="golive-form">
        <?= csrf_field() ?><input type="hidden" name="key" value="<?= e($key) ?>"><input type="hidden" name="done" value="<?= $done ? '' : '1' ?>">
        <button class="golive-box" type="submit" title="<?= $done ? 'Untick' : 'Tick as done' ?>"><?= $done ? '✓' : '' ?></button>
        <div style="flex:1">
            <strong><?= e($label) ?></strong><?php if ($link): ?> <a href="<?= e(url($link)) ?>" style="font-size:.85rem">open →</a><?php endif; ?><br>
            <small class="muted"><?= e($detail) ?></small>
            <?php if ($done): ?><br><small style="color:var(--good)">Done by <?= e($t['name'] ?? '') ?> on <?= e(date('d M Y', strtotime($t['done_at'] . ' UTC'))) ?><?= $t['note'] ? ' — ' . e($t['note']) : '' ?></small>
            <?php else: ?><input name="note" maxlength="255" placeholder="Note (optional)" style="margin-top:.3rem;font-size:.85rem"><?php endif; ?>
        </div>
    </form>
</div>
