<?php
declare(strict_types=1);

/**
 * Finance menu: grouped destinations shown inside one dropdown.
 * Each item: [route, label, one-line description, permission]. Items the person cannot open are left out.
 */
final class FinanceMenu
{
    public const GROUPS = [
        'Overview' => [
            '' => [
                ['finance/dashboard', 'Finance dashboard', 'Money held, fees, budget and big payments at a glance', 'finance.view'],
                ['fees/collections', 'Money collected', 'By channel: cash desk, MoMo, bank, online', 'fees.view'],
                ['fees/families', 'Families', 'Guardians with all their children on one account', 'fees.view'],
            ],
        ],
        'Fees' => [
            'Bills & payments' => [
                ['fees', 'Fees overview', 'This term at a glance; find a student', 'fees.view'],
                ['fees/invoices', 'Bills', 'Every bill this term', 'fees.view'],
                ['fees/debtors', 'Who owes', 'Debtors, how late, send reminders', 'fees.view'],
                ['fees/online', 'Online payments', 'Paystack / Hubtel settlements', 'fees.view'],
            ],
            'Start of term' => [
                ['fees/generate', 'Make term bills', 'Bill selected classes in one step', 'fees.manage'],
                ['fees/schedule', 'Fee schedule', 'What each level pays, Creche to JHS 3', 'fees.view'],
                ['fees/discounts', 'Discounts & scholarships', 'Full or part exemptions', 'fees.view'],
            ],
        ],
        'Cash Book' => [
            'Money in & out' => [
                ['finance', 'Cash book', 'Accounts and their balances', 'finance.view'],
                ['finance/transactions', 'All entries', 'Every payment in and out, newest first', 'finance.view'],
                ['finance/cashbook', 'Account entries', 'One account, entry by entry, with its running balance', 'finance.view'],
                ['finance/statement', 'Money in & out by category', 'Totals for each category over a period', 'finance.view'],
                ['fees/daily', 'Daily cash', "Today's counter: cash, cheques, MoMo", 'fees.view'],
                ['petty-cash', 'Petty cash', 'Small spending with receipts', 'finance.view'],
                ['fees/refunds', 'Refunds & credit notes', 'Overpayments and adjustments', 'fees.view'],
            ],
            'Control' => [
                ['finance/reconcile', 'Check bank statement', 'Reconcile the bank and MoMo accounts', 'finance.view'],
                ['finance/budget', 'Budget', 'Planned against spent', 'finance.view'],
            ],
            'Purchasing' => [
                ['requisitions', 'Requests', 'Requests to spend money and their approval', 'requisitions.create'],
                ['purchasing', 'Orders', 'Purchase orders to suppliers', 'finance.view|purchasing.order|purchasing.receive'],
                ['purchasing/received', 'Goods received', 'What arrived against each order', 'finance.view|purchasing.receive'],
                ['suppliers', 'Bills to pay', 'Supplier bills waiting for payment', 'finance.view'],
                ['suppliers/list', 'Suppliers', 'Supplier details and statements', 'finance.view'],
                ['suppliers/wht', 'Tax kept back (WHT)', 'Withholding tax owed to GRA', 'finance.view'],
            ],
        ],
        'Payroll' => [
            'Pay' => [
                ['payroll', 'Payroll engine', 'Monthly pay runs, payslips and SSNIT/PAYE schedules', 'payroll.view'],
                ['payroll/staff', 'Staff pay records', 'Salary, allowances and bank details', 'payroll.view'],
                ['payroll/annual', 'Year summary', 'Pay and deductions for the whole year', 'payroll.view'],
            ],
        ],
        'Reports' => [
            '' => [
                ['finance/reports', 'Reports studio', 'Balance sheet, income & spending, cash flow, trial balance', 'finance.view'],
                ['fixed-assets', 'Fixed assets', 'Buses, buildings, computers and depreciation', 'finance.view'],
                ['finance/close', 'Month-end', 'Close the month; board pack', 'finance.view'],
            ],
            'The books (ledger)' => [
                ['ledger', 'Summary', 'Chart of accounts and double entry', 'finance.view'],
                ['ledger/accounts', 'Accounts', 'Every ledger account and its balance', 'finance.view'],
                ['ledger/journal', 'Every entry', 'All double entries, newest first', 'finance.view'],
                ['ledger/trial-balance', 'Trial balance', 'Debits and credits side by side', 'finance.view'],
                ['ledger/balance-sheet', 'Balance sheet', 'What the school owns and owes', 'finance.view'],
                ['ledger/journals', 'Corrections (manual journals)', 'Correcting entries and their approval', 'finance.view'],
            ],
        ],
    ];

    /** Which group a page belongs to: the longest matching route prefix wins. */
    private const PREFIX = [
        'finance/dashboard' => 'Overview', 'fees/collections' => 'Overview', 'fees/families' => 'Overview', 'fees/family' => 'Overview',
        'fees' => 'Fees',
        'finance' => 'Cash Book', 'fees/daily' => 'Cash Book', 'petty-cash' => 'Cash Book', 'fees/refunds' => 'Cash Book',
        'requisitions' => 'Cash Book', 'purchasing' => 'Cash Book', 'suppliers' => 'Cash Book', 'transport/bill' => 'Fees',
        'payroll' => 'Payroll',
        'finance/reports' => 'Reports', 'ledger' => 'Reports', 'fixed-assets' => 'Reports', 'finance/close' => 'Reports', 'finance/board-pack' => 'Reports',
    ];

    /** The menu this person sees: group => [column title => [items]]. */
    public static function visible(): array
    {
        $out = [];
        foreach (self::GROUPS as $g => $cols) {
            foreach ($cols as $col => $items) {
                foreach ($items as $it) {
                    $route = strtok($it[0], '#');
                    if (array_filter(explode('|', $it[3]), 'can') && ModuleSwitch::blocked($route) === null) { // 'a|b' = either permission
                        $out[$g][$col][] = $it;
                    }
                }
            }
        }
        return $out;
    }

    public static function groupOf(string $route): ?string
    {
        $route = trim($route, '/');
        $best = null;
        $len = -1;
        foreach (self::PREFIX as $p => $g) {
            if (($route === $p || str_starts_with($route, $p . '/')) && strlen($p) > $len) {
                [$best, $len] = [$g, strlen($p)];
            }
        }
        return $best;
    }
}
