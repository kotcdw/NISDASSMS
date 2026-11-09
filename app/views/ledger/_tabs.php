<?php
$r = current_route();
$r = preg_match('#^ledger/accounts#', $r) ? 'ledger/accounts' : (str_starts_with($r, 'ledger/entries') ? 'ledger/journal' : (str_starts_with($r, 'ledger/journals') ? 'ledger/journals' : $r));
echo seg_nav(['ledger' => 'Summary', 'ledger/accounts' => 'Accounts', 'ledger/journal' => 'Every entry'],
    ['ledger/trial-balance' => 'Trial balance', 'ledger/balance-sheet' => 'Balance sheet', 'ledger/journals' => 'Corrections (manual journals)'], $r, 'The books');
