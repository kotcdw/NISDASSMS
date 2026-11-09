<?php
/* Period tabs: Today · This week · This month · This term · All. Expects $route, $period, $from, $to; optional $keep (other GET params to carry along). */
$keep = array_filter($keep ?? [], fn($v) => $v !== '' && $v !== null && $v !== 0);
$ptabs = [];
foreach (['today' => 'Today', 'week' => 'This week', 'month' => 'This month', 'term' => 'This term', 'all' => 'All'] as $pk => $pl) {
    $ptabs[$pk] = [$pl, url($route, $keep + ['period' => $pk])];
}
if ($period === 'custom') {
    $ptabs['custom'] = [fmt_date($from) . ($to !== $from ? ' – ' . fmt_date($to) : ''), url($route, $keep + ['from' => $from, 'to' => $to])];
}
echo card_tabs($ptabs, (string) $period, 6, 'Dates to show');
