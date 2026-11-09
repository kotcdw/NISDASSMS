<?php
$pnR = current_route();
$pnR = str_starts_with($pnR, 'suppliers/bills') || str_starts_with($pnR, 'suppliers/payments') ? 'suppliers'
    : (str_starts_with($pnR, 'suppliers/wht') ? 'suppliers/wht'
    : (str_starts_with($pnR, 'purchasing/received') || str_starts_with($pnR, 'purchasing/grn') ? 'purchasing/received'
    : (str_starts_with($pnR, 'purchasing') ? 'purchasing' : (str_starts_with($pnR, 'requisitions') ? 'requisitions' : $pnR))));
$pnSee = can('finance.view') || can('purchasing.order') || can('purchasing.receive');
$pnTabs = [];
$pnMore = [];
if (can('requisitions.create')) $pnTabs['requisitions'] = 'Requests';
if ($pnSee) $pnTabs['purchasing'] = 'Orders';
if (can('finance.view')) {
    $pnTabs['suppliers'] = 'Bills to pay';
    $pnMore = ['purchasing/received' => 'Goods received', 'suppliers/list' => 'Suppliers', 'suppliers/wht' => 'Tax kept back (WHT)'];
} elseif ($pnSee) {
    $pnTabs['purchasing/received'] = 'Goods received';
}
if (count($pnTabs) + count($pnMore) > 1) {
    echo seg_nav($pnTabs, $pnMore, $pnR, 'Purchasing');
}
