<?php require __DIR__ . '/_head.php'; $today = date('Y-m-d'); $q = $category ? ['category' => $category] : []; ?>
<div class="card">
    <div class="page-head" style="margin-bottom:.75rem">
        <div class="month-nav">
            <a class="btn btn-sm" href="<?= e(url('calendar', $q + ['month' => $prev])) ?>">‹</a>
            <h2 style="margin:0;min-width:170px;text-align:center"><?= e($first->format('F Y')) ?></h2>
            <a class="btn btn-sm" href="<?= e(url('calendar', $q + ['month' => $next])) ?>">›</a>
            <a class="btn btn-sm btn-ghost" href="<?= e(url('calendar', $q)) ?>">Today</a>
        </div>
    </div>
    <div class="month">
        <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat (Sabbath)', 'Sun'] as $dow): ?><div class="dow"><?= e($dow) ?></div><?php endforeach; ?>
        <?php for ($d = clone $gridStart; $d <= $gridEnd; $d->modify('+1 day')):
            $key = $d->format('Y-m-d');
            $cls = ['day'];
            if ($d->format('m') !== $first->format('m')) $cls[] = 'out';
            if ($key === $today) $cls[] = 'today';
            if ($d->format('N') === '6') $cls[] = 'sabbath';
        ?>
            <div class="<?= implode(' ', $cls) ?>">
                <?php if (can('calendar.manage')): ?>
                    <a class="dnum" href="<?= e(url('calendar/create', ['date' => $key])) ?>" title="Add event on this day"><?= $d->format('j') ?></a>
                <?php else: ?><span class="dnum"><?= $d->format('j') ?></span><?php endif; ?>
                <?php foreach ($byDay[$key] ?? [] as $ev): ?>
                    <a class="ev cat-<?= e($ev['category']) ?>" href="<?= e(url('calendar/' . $ev['id'])) ?>" title="<?= e($ev['title']) ?>"><?= $ev['start_time'] && $ev['start_date'] === $key ? e(substr($ev['start_time'], 0, 5)) . ' ' : '' ?><?= e($ev['title']) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endfor; ?>
    </div>
</div>
