<?php
$r = current_route();
if (str_starts_with($r, 'fees/family/')) $r = 'fees/families';
if (preg_match('#^fees/invoices/\d+#', $r)) $r = 'fees/invoices';
// Five everyday pages as tabs; the rest under "More"
$more = ['fees/daily' => 'Daily cash', 'fees/online' => 'Online payments', 'fees/refunds' => 'Refunds & credit notes', 'fees/schedule' => 'Fee schedule', 'fees/discounts' => 'Discounts & scholarships'];
if (can('fees.manage')) $more += ['fees/generate' => 'Make term bills'];
echo seg_nav(['fees' => 'Overview', 'fees/invoices' => 'Bills', 'fees/debtors' => 'Who owes', 'fees/collections' => 'Money collected', 'fees/families' => 'Families'], $more, $r, 'Fees');
