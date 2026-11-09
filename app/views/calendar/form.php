<?php
$v = fn(string $k, $d = '') => old($k, $ev[$k] ?? $d);
$action = $ev ? url('calendar/' . $ev['id']) : url('calendar');
$time = fn($t) => $t ? substr((string) $t, 0, 5) : '';
?>
<div class="page-center">
<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('calendar')) ?>">Calendar</a> /</div><h1><?= $ev ? 'Edit event' : 'New calendar event' ?></h1></div></div>
<div class="card">
    <form method="post" action="<?= e($action) ?>" class="form">
        <?= csrf_field() ?>
        <label class="required">Title <input name="title" required maxlength="150" value="<?= e($v('title')) ?>" placeholder="e.g. Term 1 reopens, PTA meeting, Week of Prayer"><?= field_error('title') ?></label>
        <div class="grid-2">
            <label class="required">Type
                <select name="category" required>
                    <?php foreach (CalendarEvent::CATEGORIES as $k => [$label]): ?><option value="<?= e($k) ?>"<?= selected($k, $v('category', 'event')) ?>><?= e($label) ?></option><?php endforeach; ?>
                </select><?= field_error('category') ?>
            </label>
            <?php if ($classScoped): ?>
            <label>Event audience
                <input type="hidden" name="audience" value="class">
                <input value="This class only" readonly>
            </label>
            <?php else: ?>
            <label class="required">Who should see it
                <select name="audience">
                    <?php foreach (CalendarEvent::AUDIENCES as $k => $label): ?><option value="<?= e($k) ?>"<?= selected($k, $v('audience', 'all')) ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </label>
            <?php endif; ?>
        </div>
        <div id="level-wrap"<?= $classScoped ? ' hidden' : '' ?> data-show-if="audience=level">
            <label>Level
                <select name="level_id"><option value="">— choose —</option>
                    <?php foreach ($levels as $l): ?><option value="<?= (int) $l['id'] ?>"<?= selected($l['id'], $v('level_id')) ?>><?= e($l['name']) ?></option><?php endforeach; ?>
                </select><?= field_error('level_id') ?>
            </label>
        </div>
        <div data-show-if="audience=class"<?= $v('audience', 'all') === 'class' || $classScoped ? '' : ' hidden' ?>>
            <label>Class
                <select name="class_id"><option value="">— choose —</option>
                    <?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($c['id'], $v('class_id')) ?>><?= e($c['name']) ?></option><?php endforeach; ?>
                </select><?= field_error('class_id') ?>
            </label>
        </div>
        <div class="grid-2">
            <label class="required">Start date <input type="date" name="start_date" required value="<?= e($v('start_date', $date)) ?>"><?= field_error('start_date') ?></label>
            <label>End date <input type="date" name="end_date" value="<?= e($v('end_date', $date)) ?>"><?= field_error('end_date') ?></label>
        </div>
        <div class="grid-2">
            <label>Start time (optional) <input type="time" name="start_time" value="<?= e($time($v('start_time'))) ?>"><?= field_error('start_time') ?></label>
            <label>End time <input type="time" name="end_time" value="<?= e($time($v('end_time'))) ?>"><?= field_error('end_time') ?></label>
        </div>
        <div id="sabbath-note" class="alert alert-warning"<?= old('_sabbath') ? '' : ' style="display:none"' ?>>
            This date range includes a Saturday (Sabbath). School activities are normally not held on the Sabbath.
            <label style="margin:.5rem 0 0"><input type="checkbox" name="sabbath_ok" value="1"<?= checked(old('_sabbath')) ?>> Approved — keep this date (e.g. church programme or break)</label>
        </div>
        <?php if (!$classScoped): ?><label><input type="checkbox" name="is_school_closed" value="1"<?= checked($v('is_school_closed', 0)) ?>> School is closed on these days (holiday / vacation) — attendance will not be taken</label><?php endif; ?>
        <?php if (can('messages.send')): ?>
        <?php $aud = (string) $v('audience', 'all'); $audKeys = implode(',', array_diff(array_keys(CalendarEvent::AUDIENCES), ['staff'])); ?>
        <div class="grid-2" data-show-if="audience=<?= e($audKeys) ?>"<?= $aud === 'staff' ? ' hidden' : '' ?>><!-- staff-only events never text parents -->
            <label><input type="checkbox" name="notify_parents" value="1"<?= checked($v('notify_parents', 0)) ?>> SMS a reminder to parents</label>
            <label>Days before <input type="number" name="reminder_days" min="0" max="14" value="<?= e($v('reminder_days', 1)) ?>"></label>
        </div>
        
        <?php endif; ?>
        <label>Details <textarea name="description" maxlength="2000"><?= e($v('description')) ?></textarea></label>
        <button class="btn btn-primary" type="submit">Save event</button>
        <a class="btn btn-ghost" href="<?= e(url('calendar')) ?>">Cancel</a>
    </form>
</div>
</div>
