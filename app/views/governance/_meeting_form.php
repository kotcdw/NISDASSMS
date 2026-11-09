<?php /* expects $m (array|null) */ $v = fn($k, $d = '') => old($k, $m[$k] ?? $d); ?>
<form method="post" action="<?= e(url($m ? 'governance/meetings/' . $m['id'] : 'governance/meetings')) ?>" class="form"><?= csrf_field() ?>
    <label class="required">Title <input name="title" required value="<?= e($v('title')) ?>" placeholder="e.g. Board of Governors — Term 1 meeting"></label>
    <div class="grid-2">
        <label class="required">Date <input type="date" name="meeting_date" required value="<?= e($v('meeting_date')) ?>"></label>
        <label>Start time <input type="time" name="start_time" value="<?= e(substr((string) $v('start_time'), 0, 5)) ?>"></label>
    </div>
    <div class="grid-2">
        <label class="required">Type <select name="kind"><?php foreach (Governance::MEETING_KINDS as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($v('kind', 'regular'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
        <label>Venue <input name="venue" value="<?= e($v('venue')) ?>"></label>
    </div>
    <label>Agenda <textarea name="agenda" rows="3"><?= e($v('agenda')) ?></textarea></label>
    <?php if ($m): ?><label>Key decisions / minutes summary <textarea name="decisions" rows="4"><?= e($v('decisions')) ?></textarea></label><?php endif; ?>
    <button class="btn btn-primary" type="submit"><?= $m ? 'Save changes' : 'Schedule meeting' ?></button>
</form>
