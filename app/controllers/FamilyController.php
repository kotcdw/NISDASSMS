<?php
declare(strict_types=1);

/** Family accounts (one payment for all the children, advance credit), credit notes and fee refunds. */
class FamilyController extends Controller
{
    // ------------------------------------------------------------------ families list & family account

    public function index(): void
    {
        $this->needsTable('family_credits');
        $q = trim((string) ($_GET['q'] ?? ''));
        $show = in_array($_GET['show'] ?? '', ['credit', 'owing', 'all'], true) ? $_GET['show'] : 'credit';
        $where = '1 = 1';
        $p = [];
        if ($q !== '') {
            $where .= ' AND (g.name LIKE ? OR g.phone LIKE ? OR EXISTS (SELECT 1 FROM pupil_guardian x JOIN pupils xp ON xp.id = x.pupil_id WHERE x.guardian_id = g.id AND (xp.first_name LIKE ? OR xp.last_name LIKE ? OR xp.admission_no LIKE ?)))';
            array_push($p, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%");
        }
        $rows = db()->all(
            "SELECT g.id, g.name, g.phone,
                    (SELECT COUNT(*) FROM pupil_guardian pg JOIN pupils pp ON pp.id = pg.pupil_id WHERE pg.guardian_id = g.id AND pp.status = 'active') AS kids,
                    (SELECT COALESCE(SUM(i.total - i.paid), 0) FROM invoices i WHERE i.status <> 'cancelled' AND i.pupil_id IN (SELECT pupil_id FROM pupil_guardian WHERE guardian_id = g.id)) AS owing,
                    (SELECT COALESCE(SUM(fc.amount), 0) FROM family_credits fc WHERE fc.guardian_id = g.id AND fc.status = 'posted') AS credit
             FROM guardians g WHERE $where HAVING kids > 0 " . ($show === 'credit' ? 'AND credit > 0.004' : ($show === 'owing' ? 'AND owing > 0.004' : '')) . ' ORDER BY g.name LIMIT 500',
            $p
        );
        $this->view('fees/families', ['title' => 'Family accounts', 'rows' => $rows, 'q' => $q, 'show' => $show,
            'totalCredit' => (float) db()->value("SELECT COALESCE(SUM(amount), 0) FROM family_credits WHERE status = 'posted'")]);
    }

    public function show(int $gid): void
    {
        $this->needsTable('family_credits');
        $g = Family::guardian($gid) ?? $this->notFound();
        $kids = Family::children($gid);
        if (!$kids) {
            $this->notFound();
        }
        $ids = array_map(fn($k) => (int) $k['id'], $kids);
        $in = implode(',', $ids);
        $this->view('fees/family', [
            'title' => 'Family account — ' . $g['name'], 'g' => $g, 'kids' => $kids,
            'open' => Family::openBills($gid), 'credit' => Family::credit($gid), 'free' => Family::freeCredit($gid),
            'ledger' => db()->all('SELECT fc.*, u.name AS by_name, i.invoice_no, p.first_name FROM family_credits fc LEFT JOIN users u ON u.id = fc.created_by LEFT JOIN invoices i ON i.id = fc.invoice_id LEFT JOIN pupils p ON p.id = fc.pupil_id WHERE fc.guardian_id = ? ORDER BY fc.entry_date DESC, fc.id DESC LIMIT 100', [$gid]),
            'bills' => db()->all("SELECT i.*, p.first_name FROM invoices i JOIN pupils p ON p.id = i.pupil_id WHERE i.pupil_id IN ($in) ORDER BY i.issue_date DESC, i.id DESC LIMIT 60"),
            'refunds' => db()->all('SELECT r.*, u.name AS by_name FROM fee_refunds r LEFT JOIN users u ON u.id = r.requested_by WHERE r.guardian_id = ? ORDER BY r.id DESC', [$gid]),
            'receipts' => db()->all("SELECT family_receipt AS no, MIN(paid_on) AS paid_on, SUM(amount) AS amt FROM payments WHERE status = 'completed' AND family_receipt IS NOT NULL AND pupil_id IN ($in) GROUP BY family_receipt ORDER BY MIN(id) DESC LIMIT 10"),
        ]);
    }

    /** One payment for several bills; the rest is kept as credit. */
    public function pay(int $gid): void
    {
        $this->needsTable('family_credits');
        $g = Family::guardian($gid) ?? $this->notFound();
        $amount = round((float) str_replace(',', '', (string) ($_POST['amount'] ?? '0')), 2);
        $method = (string) ($_POST['method'] ?? '');
        $date = (string) ($_POST['paid_on'] ?? date('Y-m-d'));
        $ref = mb_substr(trim((string) ($_POST['reference'] ?? '')), 0, 100);
        $alloc = [];
        foreach ((array) ($_POST['alloc'] ?? []) as $iid => $v) {
            $alloc[(int) $iid] = round((float) str_replace(',', '', (string) $v), 2);
        }
        $err = null;
        if ($amount <= 0 || $amount > 1000000) {
            $err = 'Enter the amount received.';
        } elseif (!isset(Fees::METHODS[$method]) || $method === 'online') {
            $err = 'Choose how the money was paid.';
        } elseif (in_array($method, ['momo', 'bank', 'cheque'], true) && $ref === '') {
            $err = 'Enter the transaction ID, deposit slip or cheque number.';
        } elseif (!Validator::isDate($date) || $date > date('Y-m-d') || $date < date('Y-m-d', strtotime('-60 days'))) {
            $err = 'Payment date must be within the last 60 days and not in the future.';
        } elseif (array_sum($alloc) > $amount + 0.004) {
            $err = 'The amounts shared to the bills (' . Fees::money(array_sum($alloc)) . ') are more than the money received.';
        } else {
            $err = Finance::closedError($date);
        }
        if ($err) {
            with_old($_POST);
            flash('error', $err);
            redirect('fees/family/' . $gid);
        }
        $r = Family::receive($gid, $amount, $method, $alloc, ['paid_on' => $date, 'reference' => $ref ?: null, 'payer_name' => mb_substr(trim((string) ($_POST['payer_name'] ?? '')), 0, 150) ?: $g['name']]);
        audit('family_payment', 'guardians', $gid, ['receipt' => $r['receipt'], 'amount' => $amount, 'applied' => $r['applied'], 'credit' => $r['credit']]);
        if (!empty($_POST['sms'])) {
            Notifier::smsTemplate('family_receipt', $g['phone'], [
                'guardian' => $g['name'], 'amount' => Fees::money($amount), 'receipt' => $r['receipt'],
                'applied' => Fees::money($r['applied']), 'credit' => Fees::money(Family::credit($gid)),
            ], ['name' => $g['name'], 'urgent' => true]);
            Notifier::process(5);
        }
        flash('success', Fees::money($amount) . ' received (' . $r['receipt'] . '): ' . Fees::money($r['applied']) . ' on bills' . ($r['credit'] > 0 ? ', ' . Fees::money($r['credit']) . ' kept as family credit' : '') . '.');
        redirect('fees/family/' . $gid, ['receipt' => $r['receipt']]);
    }

    /** Use the family credit on the open bills now. */
    public function apply(int $gid): void
    {
        $this->needsTable('family_credits');
        Family::guardian($gid) ?? $this->notFound();
        $used = Family::applyAll($gid);
        audit('family_credit_applied', 'guardians', $gid, ['amount' => $used]);
        flash($used > 0 ? 'success' : 'error', $used > 0 ? Fees::money($used) . ' of family credit used on the open bills.' : 'There is no free credit or no bill to use it on.');
        redirect('fees/family/' . $gid);
    }

    /** Use the family credit on one bill (button on the bill page). */
    public function applyBill(int $id): void
    {
        $this->needsTable('family_credits');
        $used = db()->transaction(fn(Database $d) => Family::applyToInvoice($d, $id));
        audit('family_credit_applied', 'invoices', $id, ['amount' => $used]);
        flash($used > 0 ? 'success' : 'error', $used > 0 ? Fees::money($used) . ' of family credit used on this bill.' : 'No free family credit to use.');
        redirect('fees/invoices/' . $id);
    }

    /** Move money paid beyond the bill total into the family credit. */
    public function toCredit(int $id): void
    {
        $this->needsTable('family_credits');
        $moved = db()->transaction(fn(Database $d) => Family::moveOverpayment($d, $id));
        audit('overpayment_to_credit', 'invoices', $id, ['amount' => $moved]);
        flash($moved > 0 ? 'success' : 'error', $moved > 0 ? Fees::money($moved) . ' moved to the family credit.' : 'Nothing to move (or the student has no parent/guardian on record).');
        redirect('fees/invoices/' . $id);
    }

    public function reverseAdvance(int $gid, int $entry): void
    {
        $this->needsTable('family_credits');
        $reason = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 200);
        $err = $reason === '' ? 'Give a reason.' : Family::reverseAdvance($entry, $reason);
        if (!$err) {
            audit('family_advance_reversed', 'family_credits', $entry, ['reason' => $reason]);
        }
        flash($err ? 'error' : 'success', $err ?? 'Advance payment reversed.');
        redirect('fees/family/' . $gid);
    }

    public function receipt(): void
    {
        $this->needsTable('family_credits');
        $no = (string) ($_GET['no'] ?? '');
        $pays = db()->all('SELECT pay.*, i.invoice_no, i.title, p.first_name, p.last_name, p.admission_no, u.name AS received_by_name FROM payments pay JOIN invoices i ON i.id = pay.invoice_id JOIN pupils p ON p.id = pay.pupil_id LEFT JOIN users u ON u.id = pay.received_by WHERE pay.family_receipt = ? ORDER BY pay.id', [$no]);
        $adv = db()->one('SELECT fc.*, u.name AS received_by_name FROM family_credits fc LEFT JOIN users u ON u.id = fc.created_by WHERE fc.receipt_no = ? AND fc.kind = \'advance\'', [$no]);
        if (!$pays && !$adv) {
            $this->notFound();
        }
        $gid = $adv ? (int) $adv['guardian_id'] : Family::guardianFor((int) $pays[0]['pupil_id']);
        $this->view('fees/print_family_receipt', ['title' => 'Receipt ' . $no, 'no' => $no, 'pays' => $pays, 'adv' => $adv, 'g' => Family::guardian((int) $gid), 'gid' => $gid, 'doc' => Docs::mark('family', $no)], 'layouts/print');
    }

    // ------------------------------------------------------------------ credit notes

    public function requestCreditNote(int $id): void
    {
        $this->needsTable('credit_notes');
        $inv = db()->one('SELECT * FROM invoices WHERE id = ?', [$id]) ?? $this->notFound();
        $amount = round((float) str_replace(',', '', (string) ($_POST['amount'] ?? '0')), 2);
        $kind = isset(Family::CN_REASONS[$_POST['reason_kind'] ?? '']) ? $_POST['reason_kind'] : 'other';
        $reason = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 255);
        $pending = (float) db()->value("SELECT COALESCE(SUM(amount), 0) FROM credit_notes WHERE invoice_id = ? AND status = 'pending'", [$id]);
        $err = null;
        if ($inv['status'] === 'cancelled') {
            $err = 'This bill is cancelled.';
        } elseif ($amount <= 0 || $amount + $pending > (float) $inv['total'] + 0.004) {
            $err = 'The credit note must be more than 0 and not more than the bill total (' . Fees::money($inv['total']) . ($pending > 0 ? ', less ' . Fees::money($pending) . ' already waiting' : '') . ').';
        } elseif ($reason === '') {
            $err = 'Say why the bill is being reduced.';
        }
        if ($err) {
            flash('error', $err);
            redirect('fees/invoices/' . $id);
        }
        $cid = db()->transaction(fn(Database $d) => $d->insert('credit_notes', [
            'cn_no' => Fees::nextNumber($d, 'CN'), 'invoice_id' => $id, 'pupil_id' => $inv['pupil_id'], 'amount' => $amount,
            'reason_kind' => $kind, 'reason' => (Family::CN_REASONS[$kind] ?? '') . ($kind !== 'other' ? ': ' : '') . $reason, 'requested_by' => Auth::id(),
        ]));
        audit('credit_note_requested', 'credit_notes', $cid, ['invoice' => $inv['invoice_no'], 'amount' => $amount]);
        Notifier::inappPermission('fees.approve', 'Credit note to approve', Fees::money($amount) . ' off bill ' . $inv['invoice_no'] . ' — ' . $reason, 'fees/refunds?tab=credit');
        flash('success', 'Credit note for ' . Fees::money($amount) . ' sent for approval.');
        redirect('fees/invoices/' . $id);
    }

    public function decideCreditNote(int $id): void
    {
        $this->needsTable('credit_notes');
        $ok = ($_POST['decision'] ?? '') === 'approve';
        $note = trim((string) ($_POST['note'] ?? ''));
        $err = (!$ok && $note === '') ? 'Give a reason for rejecting.' : Family::cnDecide($id, $ok, $note);
        if (!$err) {
            audit($ok ? 'credit_note_approved' : 'credit_note_rejected', 'credit_notes', $id, ['note' => $note]);
        }
        flash($err ? 'error' : 'success', $err ?? ($ok ? 'Credit note approved — the bill is reduced.' : 'Credit note rejected.'));
        redirect('fees/refunds', ['tab' => 'credit']);
    }

    public function printCreditNote(int $id): void
    {
        $this->needsTable('credit_notes');
        $cn = db()->one('SELECT cn.*, i.invoice_no, i.title, i.total, p.first_name, p.middle_name, p.last_name, p.admission_no, c.name AS class_name, r.name AS requested_name, a.name AS decided_name FROM credit_notes cn JOIN invoices i ON i.id = cn.invoice_id JOIN pupils p ON p.id = cn.pupil_id LEFT JOIN classes c ON c.id = i.class_id LEFT JOIN users r ON r.id = cn.requested_by LEFT JOIN users a ON a.id = cn.decided_by WHERE cn.id = ?', [$id]) ?? $this->notFound();
        $this->view('fees/print_credit_note', ['title' => 'Credit note ' . $cn['cn_no'], 'cn' => $cn, 'g' => Fees::guardian((int) $cn['pupil_id']), 'doc' => Docs::mark('credit', $cn['cn_no'])], 'layouts/print');
    }

    // ------------------------------------------------------------------ refunds

    public function refunds(): void
    {
        $this->needsTable('fee_refunds');
        $tab = ($_GET['tab'] ?? '') === 'credit' ? 'credit' : 'refunds';
        $status = (string) ($_GET['status'] ?? '');
        $data = ['title' => 'Refunds & credit notes', 'tab' => $tab, 'status' => $status, 'accounts' => Finance::accounts(true, false)];
        if ($tab === 'credit') {
            $data['rows'] = db()->all('SELECT cn.*, i.invoice_no, p.first_name, p.last_name, p.admission_no, r.name AS requested_name, a.name AS decided_name FROM credit_notes cn JOIN invoices i ON i.id = cn.invoice_id JOIN pupils p ON p.id = cn.pupil_id LEFT JOIN users r ON r.id = cn.requested_by LEFT JOIN users a ON a.id = cn.decided_by' . ($status ? ' WHERE cn.status = ?' : '') . ' ORDER BY cn.status = \'pending\' DESC, cn.id DESC LIMIT 300', $status ? [$status] : []);
        } else {
            $data['rows'] = db()->all('SELECT r.*, g.name AS guardian_name, g.phone, u.name AS requested_name, a.name AS decided_name FROM fee_refunds r JOIN guardians g ON g.id = r.guardian_id LEFT JOIN users u ON u.id = r.requested_by LEFT JOIN users a ON a.id = r.decided_by' . ($status ? ' WHERE r.status = ?' : '') . " ORDER BY FIELD(r.status, 'approved', 'pending') DESC, r.id DESC LIMIT 300", $status ? [$status] : []);
        }
        $this->view('fees/refunds', $data);
    }

    public function requestRefund(int $gid): void
    {
        $this->needsTable('fee_refunds');
        $g = Family::guardian($gid) ?? $this->notFound();
        $amount = round((float) str_replace(',', '', (string) ($_POST['amount'] ?? '0')), 2);
        $method = isset(Family::REFUND_METHODS[$_POST['method'] ?? '']) ? $_POST['method'] : 'cash';
        $reason = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 255);
        $payTo = mb_substr(trim((string) ($_POST['pay_to'] ?? '')), 0, 150) ?: $g['name'];
        $details = mb_substr(trim((string) ($_POST['pay_details'] ?? '')), 0, 150);
        $free = Family::freeCredit($gid);
        $err = null;
        if ($amount <= 0 || $amount > $free + 0.004) {
            $err = 'A refund can only come from the family credit (' . Fees::money($free) . ' free).';
        } elseif ($reason === '') {
            $err = 'Say why the money is being refunded.';
        } elseif ($method !== 'cash' && $details === '') {
            $err = 'Enter the MoMo number, bank account or cheque payee.';
        }
        if ($err) {
            flash('error', $err);
            redirect('fees/family/' . $gid);
        }
        $rid = db()->transaction(fn(Database $d) => $d->insert('fee_refunds', [
            'rf_no' => Fees::nextNumber($d, 'RF'), 'guardian_id' => $gid, 'amount' => $amount, 'reason' => $reason, 'method' => $method,
            'pay_to' => $payTo, 'pay_details' => $details ?: null, 'requested_by' => Auth::id(),
        ]));
        audit('refund_requested', 'fee_refunds', $rid, ['amount' => $amount]);
        Notifier::inappPermission('fees.approve', 'Fee refund to approve', Fees::money($amount) . ' to ' . $payTo . ' — ' . $reason, 'fees/refunds');
        flash('success', 'Refund of ' . Fees::money($amount) . ' sent for approval.');
        redirect('fees/family/' . $gid);
    }

    public function decideRefund(int $id): void
    {
        $this->needsTable('fee_refunds');
        $ok = ($_POST['decision'] ?? '') === 'approve';
        $note = trim((string) ($_POST['note'] ?? ''));
        $err = (!$ok && $note === '') ? 'Give a reason for rejecting.' : Family::refundDecide($id, $ok, $note);
        if (!$err) {
            audit($ok ? 'refund_approved' : 'refund_rejected', 'fee_refunds', $id, ['note' => $note]);
        }
        flash($err ? 'error' : 'success', $err ?? ($ok ? 'Refund approved — the Bursar can now pay it.' : 'Refund rejected.'));
        redirect('fees/refunds');
    }

    public function payRefund(int $id): void
    {
        $this->needsTable('fee_refunds');
        $date = (string) ($_POST['paid_on'] ?? date('Y-m-d'));
        if (!Validator::isDate($date) || $date > date('Y-m-d')) {
            $date = date('Y-m-d');
        }
        $err = Family::refundPay($id, (int) ($_POST['account_id'] ?? 0), $date, mb_substr(trim((string) ($_POST['reference'] ?? '')), 0, 100));
        if (!$err) {
            $r = db()->one('SELECT r.*, g.name, g.phone FROM fee_refunds r JOIN guardians g ON g.id = r.guardian_id WHERE r.id = ?', [$id]);
            audit('refund_paid', 'fee_refunds', $id, ['amount' => $r['amount']]);
            Notifier::smsTemplate('fee_refund_paid', $r['phone'], ['guardian' => $r['name'], 'amount' => Fees::money($r['amount']), 'ref' => $r['rf_no'], 'method' => Family::REFUND_METHODS[$r['method']]], ['name' => $r['name'], 'entity' => 'fee_refunds', 'entity_id' => $id]);
            Notifier::process(5);
        }
        flash($err ? 'error' : 'success', $err ?? 'Refund paid and entered in the cash book.');
        redirect('fees/refunds');
    }

    public function printRefund(int $id): void
    {
        $this->needsTable('fee_refunds');
        $r = db()->one('SELECT r.*, g.name AS guardian_name, g.phone, a.name AS account_name, u.name AS requested_name, d.name AS decided_name, pb.name AS paid_name FROM fee_refunds r JOIN guardians g ON g.id = r.guardian_id LEFT JOIN fin_accounts a ON a.id = r.account_id LEFT JOIN users u ON u.id = r.requested_by LEFT JOIN users d ON d.id = r.decided_by LEFT JOIN users pb ON pb.id = r.paid_by WHERE r.id = ?', [$id]) ?? $this->notFound();
        $this->view('fees/print_refund', ['title' => 'Refund ' . $r['rf_no'], 'r' => $r, 'kids' => Family::children((int) $r['guardian_id']), 'doc' => Docs::mark('refund', $r['rf_no'])], 'layouts/print');
    }
}
