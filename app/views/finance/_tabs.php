<?php
$r = current_route();
if (str_starts_with($r, 'finance/reconcile')) $r = 'finance/reconcile';
if (str_starts_with($r, 'finance/budget')) $r = 'finance/budget';
if (preg_match('#^finance/transactions/\d+#', $r)) $r = 'finance/transactions';
$more = ['finance/cashbook' => 'Account entries', 'finance/statement' => 'Money in & out by category'];
echo seg_nav(['finance' => 'Overview', 'finance/transactions' => 'All entries', 'finance/budget' => 'Budget', 'finance/reconcile' => 'Check bank statement'], $more, $r, 'Cash book');
