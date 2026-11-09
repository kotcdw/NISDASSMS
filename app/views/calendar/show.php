<div class="page-center">
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('calendar', ['month' => substr($ev['start_date'], 0, 7)])) ?>">Calendar</a> /</div><h1><?= e($ev['title']) ?></h1></div>
    <?php if ($canManageEvent): ?>
    <div class="actions">
        <a class="btn" href="<?= e(url('calendar/' . $ev['id'] . '/edit')) ?>">Edit</a>
        <form method="post" action="<?= e(url('calendar/' . $ev['id'] . '/delete')) ?>" data-confirm="Delete this event?"><?= csrf_field() ?><button class="btn btn-danger" type="submit">Delete</button></form>
    </div>
    <?php endif; ?>
</div>
<div class="card">
    <dl class="details">
        <dt>Date</dt><dd><?= e(fmt_date($ev['start_date'], 'l j F Y')) ?><?= $ev['end_date'] !== $ev['start_date'] ? ' – ' . e(fmt_date($ev['end_date'], 'l j F Y')) : '' ?></dd>
        <?php if ($ev['start_time']): ?><dt>Time</dt><dd><?= e(substr($ev['start_time'], 0, 5)) ?><?= $ev['end_time'] ? ' – ' . e(substr($ev['end_time'], 0, 5)) : '' ?></dd><?php endif; ?>
        <dt>Type</dt><dd><?= e(CalendarEvent::label($ev['category'])) ?><?= $ev['is_school_closed'] ? ' ' . badge('School closed', 'bad') : '' ?></dd>
        <dt>For</dt><dd><?= e($ev['audience'] === 'level' ? ($ev['level_name'] ?? '') : ($ev['audience'] === 'class' ? ($ev['class_name'] ?? '') : CalendarEvent::AUDIENCES[$ev['audience']])) ?></dd>
        <?php if ($ev['term_name']): ?><dt>Term</dt><dd><?= e($ev['term_name']) ?></dd><?php endif; ?>
        <?php if ($ev['description']): ?><dt>Details</dt><dd><?= nl2br(e($ev['description'])) ?></dd><?php endif; ?>
        <?php if (can('staff')): ?><dt>Added by</dt><dd><?= e($ev['author'] ?? '—') ?>, <?= e(fmt_date($ev['created_at'], 'd M Y H:i')) ?></dd><?php endif; ?>
    </dl>
</div>
</div>
