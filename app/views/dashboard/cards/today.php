<?php
/* Today strip: what is happening in school today. */
$parts = [];
if (isset($d['events'])) {
    $parts[] = ['Events', $d['events'] ? implode('', array_map(fn($e) => '<a href="' . e(url($e['href'])) . '">' . e($e['text']) . '</a>', $d['events'])) : '<span class="muted">None today</span>'];
}
if (isset($d['away'])) {
    $parts[] = ['Staff away', '<a href="' . e(url('staff-leave')) . '">' . (int) $d['away']['leave'] . ' on leave</a><a href="' . e(url('movements')) . '">' . (int) $d['away']['out'] . ' out of school</a>'];
}
if (isset($d['visitors'])) {
    $parts[] = ['Visitors', '<a href="' . e(url('visitors')) . '">' . (int) $d['visitors'] . ' on site now</a>'];
}
if (!empty($d['registers'])) {
    $parts[] = ['My registers', implode('', array_map(fn($c) => '<a href="' . e(url('attendance/register/' . $c['id'])) . '" class="' . ($c['taken'] ? 'is-done' : 'is-todo') . '">' . ($c['taken'] ? '✓ ' : '') . e($c['name']) . ($c['taken'] ? '' : ' — take register') . '</a>', $d['registers']))];
}
if (!empty($d['lessons'])) {
    $parts[] = ['My lessons', implode('', array_map(fn($l) => '<span>' . e($l) . '</span>', $d['lessons']))];
}
?>
<section class="an-today" data-card="today" aria-label="Today">
    <h2><?= e(date('l j F')) ?></h2>
    <?php foreach ($parts as [$label, $html]): ?><div class="an-tg"><span class="an-tl"><?= e($label) ?></span><span class="an-tv"><?= $html ?></span></div><?php endforeach; ?>
    <?php if (!$parts): ?><div class="an-tg"><span class="muted">Nothing scheduled for you today.</span></div><?php endif; ?>
</section>
