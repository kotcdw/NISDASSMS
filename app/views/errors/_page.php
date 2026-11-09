<?php /* Shared error page. Expects $code, $tone, $heading, $lead, $points (list), $actions ([label, href, primary?]) */ ?>
<div class="err-page tone-<?= e($tone) ?>">
    <div class="err-card">
        <div class="err-ico" aria-hidden="true">
            <?php if ($code === 403): ?><svg viewBox="0 0 24 24"><rect x="4.5" y="10.5" width="15" height="10" rx="2.2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M8 10.5V7.8a4 4 0 0 1 8 0v2.7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="15.3" r="1.5" fill="currentColor"/></svg>
            <?php else: ?><svg viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="m15.5 15.5 5 5M8.3 8.3l4.4 4.4M12.7 8.3l-4.4 4.4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg><?php endif; ?>
        </div>
        <p class="err-code">Error <?= (int) $code ?></p>
        <h1><?= e($heading) ?></h1>
        <p class="err-lead"><?= e($lead) ?></p>
        <?php if ($points): ?><ul class="err-points"><?php foreach ($points as $pt): ?><li><?= e($pt) ?></li><?php endforeach; ?></ul><?php endif; ?>
        <div class="err-actions">
            <button type="button" class="btn" data-back>← Go back</button>
            <?php foreach ($actions as [$label, $href, $primary]): ?><a class="btn<?= $primary ? ' btn-primary' : '' ?>" href="<?= e($href) ?>"><?= e($label) ?></a><?php endforeach; ?>
        </div>
    </div>
</div>
