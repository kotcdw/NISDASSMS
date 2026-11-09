<?php require BASE_PATH . '/app/views/layouts/_public_url.php'; ?>
<div class="page-center">
<?php require __DIR__ . '/_tabs.php'; $in = $in ?? []; $v = fn($k, $d = '') => $in[$k] ?? old($k, $d); ?>
<?php if ($preview): ?>
<div class="card" style="border-color:var(--brand);max-width:820px">
    <h2>Check before sending</h2>
    <dl class="details">
        <dt>To</dt><dd><?= e(Audience::label((string) $in['audience'])) ?> — <strong><?= (int) $preview['count'] ?> phone numbers</strong> (one per family)</dd>
        <dt>Length</dt><dd><?= mb_strlen((string) $in['body']) ?> characters = <?= (int) $preview['segments'] ?> SMS each</dd>
        <dt>Estimated cost</dt><dd>GH₵ <?= number_format($preview['cost'], 2) ?> <small>(at GH₵ <?= e(setting('sms_cost', '0.035')) ?> per SMS — set in SMS settings)</small></dd>
        <?php if ($preview['scheduled']): ?><dt>Send at</dt><dd><?= e(fmt_date($preview['scheduled'], 'l j F Y, H:i')) ?></dd><?php endif; ?>
        <dt>First recipients</dt><dd><?php foreach ($preview['sample'] as $ph => $nm): ?><?= e($nm ?: '—') ?> (<?= e($ph) ?>)<br><?php endforeach; ?></dd>
        <dt>Message</dt><dd style="white-space:pre-wrap;background:var(--bg);padding:.6rem;border-radius:8px"><?= e($in['body']) ?></dd>
    </dl>
    <form method="post" action="<?= e(url('messages')) ?>" style="margin-top:1rem">
        <?= csrf_field() ?>
        <?php foreach (['title', 'audience', 'custom', 'body', 'schedule_date', 'schedule_time'] as $k): ?>
            <input type="hidden" name="<?= $k ?>" value="<?= e($in[$k] ?? '') ?>">
        <?php endforeach; ?>
        <input type="hidden" name="confirm" value="1">
        <button class="btn btn-primary" type="submit">Send to <?= (int) $preview['count'] ?> recipients</button>
        <a class="btn btn-ghost" href="<?= e(url('messages/compose')) ?>">Start over</a>
    </form>
</div>
<?php else: ?>
<div class="card">
    <form method="post" action="<?= e(url('messages')) ?>" class="form">
        <?= csrf_field() ?>
        <label class="required">Title (for your records) <input name="title" required maxlength="150" value="<?= e($v('title')) ?>" placeholder="e.g. PTA meeting reminder"></label>
        <label class="required">Send to
            <select name="audience" required>
                <?php foreach ($options as $k => $label): ?><option value="<?= e($k) ?>"<?= selected($k, $v('audience', 'parents:all')) ?>><?= e($label) ?></option><?php endforeach; ?>
            </select><?= field_error('audience') ?>
        </label>
        <label>Phone numbers (only for “Type phone numbers”) <textarea name="custom" rows="2" placeholder="0244123456, 0201234567"><?= e($v('custom')) ?></textarea></label>
        <label class="required">Message <textarea name="body" required maxlength="612" rows="5" data-sms-counter><?= e($v('body')) ?></textarea><?= field_error('body') ?></label>
        
        <div class="grid-2">
            <label>Schedule date (optional) <input type="date" name="schedule_date" min="<?= date('Y-m-d') ?>" value="<?= e($v('schedule_date')) ?>"><?= field_error('schedule_date') ?></label>
            <label>Time <input type="time" name="schedule_time" value="<?= e($v('schedule_time', '07:00')) ?>"></label>
        </div>
        
        <button class="btn btn-primary" type="submit">Preview</button>
    </form>
</div>
<?php endif; ?>
</div>
