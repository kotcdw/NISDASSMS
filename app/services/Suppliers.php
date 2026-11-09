<?php
declare(strict_types=1);

/**
 * Suppliers and the bills the school owes them. Paying a bill withholds Ghana withholding tax (WHT) where it applies:
 * the supplier gets the net amount, the school keeps the WHT and pays it to GRA later ("WHT to pay to GRA").
 * Rates and the threshold are settings (defaults: goods 3%, works 5%, services 7.5%, bills above GH₵ 2,000) —
 * confirm them with GRA whenever the law changes.
 */
final class Suppliers
{
    public const TYPES = ['goods' => 'Goods (supply of items)', 'works' => 'Works (building, repairs, construction)', 'services' => 'Services (labour, consultancy, transport …)', 'exempt' => 'Exempt / no withholding'];
    public const STATUS = ['unpaid' => ['Unpaid', 'bad'], 'part' => ['Part paid', 'warn'], 'paid' => ['Paid', 'good'], 'cancelled' => ['Cancelled', 'neutral']];
    public const DEFAULT_RATES = ['goods' => '3', 'works' => '5', 'services' => '7.5'];

    public static function rate(string $type): float
    {
        return $type === 'exempt' ? 0.0 : (float) setting('wht_' . $type, self::DEFAULT_RATES[$type] ?? '0');
    }

    public static function threshold(): float
    {
        return (float) setting('wht_threshold', '2000');
    }

    /** [rate, amount] of WHT for a bill. */
    public static function wht(float $amount, string $type, bool $exempt = false): array
    {
        if ($exempt || $type === 'exempt' || $amount <= self::threshold()) {
            return [0.0, 0.0];
        }
        $r = self::rate($type);
        return [$r, round($amount * $r / 100, 2)];
    }

    /** Bills above this amount need the Head's approval before they can be paid (0 = no approval step). */
    public static function approvalLimit(): float
    {
        return (float) setting('pur_pay_approval_above', '5000');
    }

    public static function needsApproval(array $b): bool
    {
        return array_key_exists('pay_approved_at', $b) && self::approvalLimit() > 0 && (float) $b['amount'] > self::approvalLimit();
    }

    public static function approvePay(int $billId): ?string
    {
        $b = db()->one('SELECT * FROM supplier_bills WHERE id = ?', [$billId]);
        if (!$b || in_array($b['status'], ['paid', 'cancelled'], true)) {
            return 'This bill cannot be approved for payment.';
        }
        if (!empty($b['pay_approved_at'])) {
            return 'This bill is already approved for payment.';
        }
        if ((int) $b['created_by'] === (int) Auth::id() && Auth::role() !== 'super_admin') {
            return 'Someone else must approve a bill you recorded.';
        }
        db()->update('supplier_bills', ['pay_approved_by' => Auth::id(), 'pay_approved_at' => date('Y-m-d H:i:s')], 'id = ?', [$billId]);
        return null;
    }

    public static function bill(int $id): ?array
    {
        $po = Purchasing::ready();
        return db()->one(
            'SELECT b.*, s.name AS supplier_name, s.tin, s.phone AS supplier_phone, s.address AS supplier_address, s.pay_details,
                    c.name AS category_name, u.name AS created_name, r.req_no' . ($po ? ', po.po_no, ap.name AS approved_name' : '') . '
             FROM supplier_bills b JOIN suppliers s ON s.id = b.supplier_id LEFT JOIN fin_categories c ON c.id = b.category_id
             LEFT JOIN users u ON u.id = b.created_by LEFT JOIN requisitions r ON r.id = b.requisition_id'
             . ($po ? ' LEFT JOIN purchase_orders po ON po.id = b.po_id LEFT JOIN users ap ON ap.id = b.pay_approved_by' : '') . ' WHERE b.id = ?',
            [$id]
        );
    }

    public static function balance(array $b): float
    {
        return $b['status'] === 'cancelled' ? 0.0 : round((float) $b['amount'] - (float) $b['paid_gross'], 2);
    }

    /** Pay all or part of a bill. Returns [payment id, null] or [null, error]. */
    public static function pay(int $billId, float $gross, int $accountId, string $date, string $reference): array
    {
        if ($err = Finance::closedError($date)) {
            return [null, $err];
        }
        try {
            $pid = db()->transaction(function (Database $d) use ($billId, $gross, $accountId, $date, $reference) {
                $b = $d->one('SELECT b.*, s.name AS supplier_name FROM supplier_bills b JOIN suppliers s ON s.id = b.supplier_id WHERE b.id = ? FOR UPDATE', [$billId]);
                $left = self::balance($b);
                if ($b['status'] === 'cancelled' || $b['status'] === 'paid') {
                    throw new DomainException('This bill is ' . ($b['status'] === 'paid' ? 'already paid' : 'cancelled') . '.');
                }
                if ($gross <= 0 || $gross > $left + 0.004) {
                    throw new DomainException('Pay between 0.01 and the balance of ' . Fees::money($left) . '.');
                }
                if (self::needsApproval($b) && empty($b['pay_approved_at'])) {
                    throw new DomainException('Bills above ' . Fees::money(self::approvalLimit()) . ' must be approved for payment by the Head first.');
                }
                if (!empty($b['po_id'])) { // three-way match: only what was ordered, received and invoiced
                    $m = Purchasing::match($billId, $d);
                    if ((float) $b['paid_gross'] + $gross > $m['payable'] + 0.004) {
                        $can = max(0.0, round($m['payable'] - (float) $b['paid_gross'], 2));
                        throw new DomainException('Not ready to pay: ' . ($m['problems'] ? implode('; ', $m['problems']) . '. ' : '') . ($can > 0 ? 'You can pay up to ' . Fees::money($can) . ' for what was received.' : 'Nothing more can be paid until the goods are received.'));
                    }
                }
                $whtDone = (float) $d->value("SELECT COALESCE(SUM(wht), 0) FROM supplier_payments WHERE bill_id = ? AND status = 'posted'", [$billId]);
                $last = abs($gross - $left) < 0.005;
                $wht = $last ? round((float) $b['wht_amount'] - $whtDone, 2) : round($gross * (float) $b['wht_amount'] / max(0.01, (float) $b['amount']), 2);
                $net = round($gross - $wht, 2);
                if (!$d->value('SELECT id FROM fin_accounts WHERE id = ? AND is_active = 1', [$accountId])) {
                    throw new DomainException('Choose the account the money is paid from.');
                }
                if (Finance::balance($accountId) + 0.004 < $net) {
                    throw new DomainException('That account has only ' . Fees::money(Finance::balance($accountId)) . '.');
                }
                $no = Fees::nextNumber($d, 'PV');
                $pid = $d->insert('supplier_payments', [
                    'pv_no' => $no, 'bill_id' => $billId, 'pay_date' => $date, 'account_id' => $accountId, 'gross' => $gross, 'wht' => $wht, 'net' => $net,
                    'reference' => $reference ?: null, 'created_by' => Auth::id(),
                ]);
                $txn = Finance::post($d, [
                    'txn_no' => $no, 'kind' => 'expense', 'txn_date' => $date, 'account_id' => $accountId, 'category_id' => $b['category_id'],
                    'amount' => $net, 'description' => $b['supplier_name'] . ': ' . $b['description'] . ' (' . $b['bill_no'] . ($wht > 0 ? ', WHT ' . number_format($wht, 2) . ' withheld' : '') . ')',
                    'party' => $b['supplier_name'], 'reference' => $reference ?: $b['supplier_ref'], 'source' => 'supplier_payment', 'source_id' => $pid,
                    'department' => $b['department'] ?? null,
                ]);
                $d->update('supplier_payments', ['txn_id' => $txn], 'id = ?', [$pid]);
                self::recalc($d, $billId);
                return $pid;
            });
            return [$pid, null];
        } catch (DomainException $e) {
            return [null, $e->getMessage()];
        }
    }

    /** Paid amount and status of a bill; a linked requisition is retired when its bill is fully paid. */
    public static function recalc(Database $d, int $billId): void
    {
        $b = $d->one('SELECT * FROM supplier_bills WHERE id = ?', [$billId]);
        $paid = (float) $d->value("SELECT COALESCE(SUM(gross), 0) FROM supplier_payments WHERE bill_id = ? AND status = 'posted'", [$billId]);
        $status = $b['status'] === 'cancelled' ? 'cancelled' : ($paid <= 0.004 ? 'unpaid' : ($paid + 0.004 >= (float) $b['amount'] ? 'paid' : 'part'));
        $d->update('supplier_bills', ['paid_gross' => $paid, 'status' => $status], 'id = ?', [$billId]);
        if (!empty($b['po_id'])) {
            Purchasing::refresh($d, (int) $b['po_id']);
        }
        if ($b['requisition_id']) {
            if ($status === 'paid') {
                $d->update('requisitions', ['status' => 'retired', 'paid_amount' => $paid, 'spent_amount' => $paid, 'paid_by' => Auth::id(), 'paid_at' => date('Y-m-d H:i:s'), 'retired_at' => date('Y-m-d H:i:s'), 'retire_note' => 'Paid to the supplier on bill ' . $b['bill_no']], "id = ? AND status IN ('approved','retired')", [$b['requisition_id']]);
            } else {
                $d->update('requisitions', ['status' => 'approved', 'paid_amount' => 0, 'spent_amount' => null, 'retired_at' => null, 'retire_note' => null], "id = ? AND status = 'retired' AND retire_note LIKE 'Paid to the supplier%'", [$b['requisition_id']]);
            }
        }
    }

    public static function voidPayment(int $pid, string $reason): ?string
    {
        $p = db()->one('SELECT * FROM supplier_payments WHERE id = ?', [$pid]);
        if (!$p || $p['status'] !== 'posted') {
            return 'This payment cannot be cancelled.';
        }
        if ($p['wht_remit_id']) {
            return 'The tax withheld on this payment has already been paid to GRA.';
        }
        if ($err = Finance::closedError($p['pay_date'])) {
            return $err;
        }
        if (db()->value('SELECT recon_id FROM fin_transactions WHERE id = ?', [$p['txn_id']])) {
            return 'This payment is ticked on a completed bank reconciliation. Reopen that reconciliation first.';
        }
        db()->transaction(function (Database $d) use ($p, $reason) {
            $d->update('supplier_payments', ['status' => 'void', 'void_reason' => mb_substr($reason, 0, 255)], 'id = ?', [$p['id']]);
            Finance::voidSource($d, 'supplier_payment', (int) $p['id'], 'Supplier payment cancelled: ' . $reason);
            self::recalc($d, (int) $p['bill_id']);
        });
        return null;
    }

    /** WHT withheld and not yet paid to GRA. */
    public static function whtOwed(): array
    {
        return db()->all(
            "SELECT p.*, b.bill_no, b.description, b.category_id, b.supply_type, b.wht_rate, b.department, s.name AS supplier_name, s.tin
             FROM supplier_payments p JOIN supplier_bills b ON b.id = p.bill_id JOIN suppliers s ON s.id = b.supplier_id
             WHERE p.status = 'posted' AND p.wht > 0 AND p.wht_remit_id IS NULL ORDER BY p.pay_date, p.id"
        );
    }

    /** Pay the withheld tax to GRA: one cash book entry per payment (so it lands in the right spending category). */
    public static function remit(int $accountId, string $date, string $reference): array
    {
        if ($err = Finance::closedError($date)) {
            return [null, $err];
        }
        $owed = self::whtOwed();
        $total = round(array_sum(array_map(fn($p) => (float) $p['wht'], $owed)), 2);
        if ($total <= 0) {
            return [null, 'There is no withholding tax to pay.'];
        }
        if (Finance::balance($accountId) + 0.004 < $total) {
            return [null, 'That account has only ' . Fees::money(Finance::balance($accountId)) . '.'];
        }
        $id = db()->transaction(function (Database $d) use ($owed, $total, $accountId, $date, $reference) {
            $no = Fees::nextNumber($d, 'WHT');
            $rid = $d->insert('wht_remittances', ['wr_no' => $no, 'remit_date' => $date, 'account_id' => $accountId, 'amount' => $total, 'reference' => $reference ?: null, 'created_by' => Auth::id()]);
            foreach ($owed as $p) {
                Finance::post($d, [
                    'kind' => 'expense', 'txn_date' => $date, 'account_id' => $accountId, 'category_id' => $p['category_id'], 'amount' => $p['wht'],
                    'description' => 'WHT to GRA (' . $no . ') on ' . $p['pv_no'] . ' — ' . $p['supplier_name'], 'party' => 'Ghana Revenue Authority',
                    'reference' => $reference ?: null, 'source' => 'wht_remit', 'source_id' => $p['id'], 'department' => $p['department'] ?? null,
                ]);
                $d->update('supplier_payments', ['wht_remit_id' => $rid], 'id = ?', [$p['id']]);
            }
            return $rid;
        });
        return [$id, null];
    }
}
