<?php
declare(strict_types=1);

/** Fees: schedule, bills, payments, receipts, statements, debtors and collection reports. */
class FeeController extends Controller
{
    // ---------------------------------------------------------------- overview

    public function index(): void
    {
        $term = $this->term();
        $tid = $term['id'] ?? 0;
        $sum = db()->one(
            "SELECT COUNT(*) AS bills, COALESCE(SUM(total), 0) AS billed, COALESCE(SUM(paid), 0) AS paid,
                    COALESCE(SUM(GREATEST(total - paid, 0)), 0) AS outstanding, COALESCE(SUM(status IN ('unpaid','part')), 0) AS owing
             FROM invoices WHERE term_id = ? AND status <> 'cancelled'",
            [$tid]
        );
        $today = db()->one("SELECT COUNT(*) AS n, COALESCE(SUM(amount), 0) AS amt FROM payments WHERE paid_on = CURDATE() AND status = 'completed'");
        $byClass = db()->all(
            "SELECT c.id, c.name, COUNT(i.id) AS bills, COALESCE(SUM(i.total), 0) AS billed, COALESCE(SUM(i.paid), 0) AS paid
             FROM invoices i JOIN classes c ON c.id = i.class_id JOIN levels l ON l.id = c.level_id
             WHERE i.term_id = ? AND i.status <> 'cancelled' GROUP BY c.id, c.name, l.sort_order ORDER BY l.sort_order, c.name",
            [$tid]
        );
        $recent = db()->all(
            'SELECT pay.*, p.first_name, p.last_name, p.admission_no, i.invoice_no FROM payments pay
             JOIN pupils p ON p.id = pay.pupil_id JOIN invoices i ON i.id = pay.invoice_id ORDER BY pay.id DESC LIMIT 8'
        );
        $q = trim((string) ($_GET['q'] ?? ''));
        $found = [];
        if ($q !== '') {
            $found = db()->all(
                "SELECT p.id, p.admission_no, p.first_name, p.middle_name, p.last_name, p.status,
                        (SELECT COALESCE(SUM(total - paid), 0) FROM invoices i WHERE i.pupil_id = p.id AND i.status <> 'cancelled') AS balance
                 FROM pupils p WHERE p.deleted_at IS NULL AND (p.admission_no LIKE ? OR CONCAT_WS(' ', p.first_name, p.last_name) LIKE ? OR p.last_name LIKE ?)
                 ORDER BY p.status = 'active' DESC, p.last_name LIMIT 20",
                ["%$q%", "%$q%", "%$q%"]
            );
        }
        $this->view('fees/index', [
            'title' => 'Fees', 'term' => $term, 'terms' => $this->terms(), 'sum' => $sum, 'today' => $today, 'byClass' => $byClass,
            'recent' => $recent, 'q' => $q, 'found' => $found,
            'pendingOnline' => (int) db()->value("SELECT COUNT(*) FROM online_payments WHERE status = 'initiated' AND created_at < (NOW() - INTERVAL 10 MINUTE) AND created_at > (NOW() - INTERVAL 7 DAY)"),
            'gateway' => PaymentGateway::active(),
        ]);
    }

    // ---------------------------------------------------------------- schedule

    public function schedule(): void
    {
        $levels = db()->all('SELECT * FROM levels ORDER BY sort_order');
        $items = db()->all('SELECT * FROM fee_items ORDER BY is_active DESC, sort_order, name');
        $grid = [];
        foreach (db()->all('SELECT * FROM fee_structures') as $r) {
            $grid[$r['level_id']][$r['fee_item_id']] = $r['amount'];
        }
        $this->view('fees/schedule', ['title' => 'Fee schedule', 'levels' => $levels, 'items' => $items, 'grid' => $grid]);
    }

    public function saveSchedule(): void
    {
        $levels = array_column(db()->all('SELECT id FROM levels'), 'id');
        $items = array_column(db()->all('SELECT id FROM fee_items'), 'id');
        $amounts = is_array($_POST['amount'] ?? null) ? $_POST['amount'] : [];
        $n = 0;
        db()->transaction(function (Database $d) use ($levels, $items, $amounts, &$n) {
            foreach ($levels as $lid) {
                foreach ($items as $fid) {
                    if (!isset($amounts[$lid][$fid])) {
                        continue;
                    }
                    $raw = str_replace(',', '', trim((string) $amounts[$lid][$fid]));
                    $amt = $raw === '' ? 0.0 : (is_numeric($raw) ? round((float) $raw, 2) : -1);
                    if ($amt < 0 || $amt > 100000) {
                        continue;
                    }
                    $d->run(
                        'INSERT INTO fee_structures (level_id, fee_item_id, amount) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE amount = VALUES(amount)',
                        [$lid, $fid, $amt]
                    );
                    $n++;
                }
            }
        });
        setting_set('fee_schedule_confirmed', isset($_POST['confirm_schedule']) ? '1' : '0');
        audit('fee_schedule_saved', 'fee_structures', null, ['cells' => $n]);
        flash('success', 'Fee schedule saved. New amounts apply to bills generated from now on.');
        redirect('fees/schedule');
    }

    public function saveItem(int $id = 0): void
    {
        $in = $this->only(['name', 'code', 'frequency', 'sort_order']);
        $in['code'] = strtoupper((string) $in['code']);
        $errors = Validator::check($in, ['name' => 'required|max:80', 'code' => 'required|max:12', 'frequency' => 'required|in:termly,yearly,once']);
        if (!$errors && !preg_match('/^[A-Z0-9_-]+$/', $in['code'])) {
            $errors['code'] = 'Letters and numbers only.';
        }
        $dup = db()->value('SELECT id FROM fee_items WHERE (name = ? OR code = ?) AND id <> ?', [$in['name'], $in['code'], $id]);
        if ($errors || $dup) {
            flash('error', $dup ? 'Another fee item already uses that name or code.' : implode(' ', $errors));
            redirect('fees/schedule');
        }
        $data = [
            'name' => $in['name'], 'code' => $in['code'], 'frequency' => $in['frequency'], 'sort_order' => (int) $in['sort_order'],
            'discountable' => isset($_POST['discountable']) ? 1 : 0, 'is_active' => $id && !isset($_POST['is_active']) ? 0 : 1,
        ];
        if ($id) {
            db()->update('fee_items', $data, 'id = ?', [$id]);
        } else {
            $id = db()->insert('fee_items', $data);
        }
        audit('fee_item_saved', 'fee_items', $id, $data);
        flash('success', 'Fee item saved.');
        redirect('fees/schedule');
    }

    // ---------------------------------------------------------------- discounts

    public function discounts(): void
    {
        $discounts = db()->all('SELECT d.*, (SELECT COUNT(*) FROM pupil_discounts pd WHERE pd.discount_id = d.id) AS pupils FROM discounts d ORDER BY d.is_active DESC, d.name');
        $assigned = db()->all(
            'SELECT pd.*, d.name AS discount_name, p.id AS pupil_id, p.first_name, p.last_name, p.admission_no FROM pupil_discounts pd
             JOIN discounts d ON d.id = pd.discount_id JOIN pupils p ON p.id = pd.pupil_id WHERE p.deleted_at IS NULL ORDER BY d.name, p.last_name'
        );
        $this->view('fees/discounts', ['title' => 'Discounts & scholarships', 'discounts' => $discounts, 'assigned' => $assigned]);
    }

    public function saveDiscount(int $id = 0): void
    {
        $in = $this->only(['name', 'kind', 'value']);
        $errors = Validator::check($in, ['name' => 'required|max:80', 'kind' => 'required|in:percent,fixed', 'value' => 'required']);
        $val = (float) ($in['value'] ?? 0);
        if (!$errors && (!is_numeric($in['value']) || $val <= 0 || ($in['kind'] === 'percent' && $val > 100) || $val > 100000)) {
            $errors['value'] = 'Enter a value above 0 (at most 100 for a percentage).';
        }
        if (!$errors && db()->value('SELECT id FROM discounts WHERE name = ? AND id <> ?', [$in['name'], $id])) {
            $errors['name'] = 'That name is already used.';
        }
        if ($errors) {
            flash('error', implode(' ', $errors));
            redirect('fees/discounts');
        }
        $data = ['name' => $in['name'], 'kind' => $in['kind'], 'value' => round($val, 2), 'is_sibling' => isset($_POST['is_sibling']) ? 1 : 0,
                 'is_active' => $id && !isset($_POST['is_active']) ? 0 : 1];
        if ($id) {
            db()->update('discounts', $data, 'id = ?', [$id]);
        } else {
            $id = db()->insert('discounts', $data);
        }
        audit('discount_saved', 'discounts', $id, $data);
        flash('success', 'Discount saved. It applies to bills generated from now on.');
        redirect('fees/discounts');
    }

    public function savePupilDiscounts(int $id): void
    {
        $pupil = $this->pupil($id);
        $valid = array_map('intval', array_column(db()->all('SELECT id FROM discounts WHERE is_sibling = 0'), 'id'));
        $chosen = array_values(array_intersect($valid, array_map('intval', (array) ($_POST['discounts'] ?? []))));
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 160) ?: null;
        db()->transaction(function (Database $d) use ($id, $chosen, $note) {
            $d->run('DELETE pd FROM pupil_discounts pd JOIN discounts d ON d.id = pd.discount_id WHERE pd.pupil_id = ? AND d.is_sibling = 0', [$id]);
            foreach ($chosen as $did) {
                $d->insert('pupil_discounts', ['pupil_id' => $id, 'discount_id' => $did, 'note' => $note]);
            }
        });
        audit('pupil_discounts_saved', 'pupils', $id, ['discounts' => $chosen]);
        flash('success', 'Discounts for ' . full_name($pupil) . ' saved. They apply to the next bill generated.');
        redirect('fees/pupil/' . $id);
    }

    // ---------------------------------------------------------------- bills

    public function invoices(): void
    {
        $f = [
            'term' => (int) ($_GET['term'] ?? ($this->term()['id'] ?? 0)), 'class' => (int) ($_GET['class'] ?? 0),
            'status' => (string) ($_GET['status'] ?? ''), 'kind' => (string) ($_GET['kind'] ?? ''), 'q' => trim((string) ($_GET['q'] ?? '')),
        ];
        $sql = 'SELECT i.*, p.first_name, p.middle_name, p.last_name, p.admission_no, c.name AS class_name FROM invoices i
                JOIN pupils p ON p.id = i.pupil_id LEFT JOIN classes c ON c.id = i.class_id WHERE 1 = 1';
        $p = [];
        if ($f['term']) {
            $sql .= ' AND i.term_id = ?';
            $p[] = $f['term'];
        }
        if ($f['class']) {
            $sql .= ' AND i.class_id = ?';
            $p[] = $f['class'];
        }
        if (isset(Fees::STATUS[$f['status']])) {
            $sql .= ' AND i.status = ?';
            $p[] = $f['status'];
        } elseif ($f['status'] === 'owing') {
            $sql .= " AND i.status IN ('unpaid','part')";
        }
        if (in_array($f['kind'], ['term', 'admission', 'other'], true)) {
            $sql .= ' AND i.kind = ?';
            $p[] = $f['kind'];
        }
        if ($f['q'] !== '') {
            $sql .= " AND (i.invoice_no LIKE ? OR p.admission_no LIKE ? OR CONCAT_WS(' ', p.first_name, p.last_name) LIKE ?)";
            array_push($p, "%{$f['q']}%", "%{$f['q']}%", "%{$f['q']}%");
        }
        [$rows, $pager] = paginate($sql . ' ORDER BY i.id DESC', $p, 50);
        $this->view('fees/invoices', ['title' => 'Bills', 'rows' => $rows, 'pager' => $pager]);
    }

    public function showInvoice(int $id): void
    {
        $inv = $this->invoice($id);
        $this->view('fees/invoice', [
            'title' => 'Bill ' . $inv['invoice_no'], 'inv' => $inv,
            'lines' => db()->all('SELECT * FROM invoice_lines WHERE invoice_id = ? ORDER BY id', [$id]),
            'payments' => db()->all('SELECT pay.*, u.name AS received_by_name, r.name AS reversed_by_name FROM payments pay LEFT JOIN users u ON u.id = pay.received_by LEFT JOIN users r ON r.id = pay.reversed_by WHERE pay.invoice_id = ? ORDER BY pay.id', [$id]),
            'online' => db()->all('SELECT * FROM online_payments WHERE invoice_id = ? ORDER BY id DESC LIMIT 20', [$id]),
            'items' => db()->all('SELECT * FROM fee_items WHERE is_active = 1 ORDER BY sort_order'),
            'guardian' => Fees::guardian((int) $inv['pupil_id']),
            'gid' => Family::ready() ? Family::guardianFor((int) $inv['pupil_id']) : null,
            'creditNotes' => Family::ready() ? db()->all('SELECT cn.*, u.name AS requested_name FROM credit_notes cn LEFT JOIN users u ON u.id = cn.requested_by WHERE cn.invoice_id = ? ORDER BY cn.id', [$id]) : [],
        ]);
    }

    public function printInvoice(int $id): void
    {
        $inv = $this->invoice($id);
        $this->view('fees/print_invoice', [
            'title' => 'Bill ' . $inv['invoice_no'], 'inv' => $inv,
            'lines' => db()->all('SELECT * FROM invoice_lines WHERE invoice_id = ? ORDER BY id', [$id]),
            'payments' => db()->all("SELECT * FROM payments WHERE invoice_id = ? AND status = 'completed' ORDER BY id", [$id]),
            'guardian' => Fees::guardian((int) $inv['pupil_id']),
        ], 'layouts/print');
    }

    public function addLine(int $id): void
    {
        $inv = $this->invoice($id);
        if ($inv['status'] === 'cancelled') {
            flash('error', 'This bill is cancelled.');
            redirect('fees/invoices/' . $id);
        }
        $itemId = (int) ($_POST['fee_item_id'] ?? 0);
        $item = $itemId ? db()->one('SELECT * FROM fee_items WHERE id = ?', [$itemId]) : null;
        $desc = mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 150) ?: ($item['name'] ?? '');
        $amount = round((float) str_replace(',', '', (string) ($_POST['amount'] ?? '0')), 2);
        $disc = round((float) str_replace(',', '', (string) ($_POST['discount'] ?? '0')), 2);
        if ($desc === '' || $amount <= 0 || $amount > 100000 || $disc < 0 || $disc > $amount) {
            flash('error', 'Enter a description and an amount above 0 (the discount cannot exceed the amount).');
            redirect('fees/invoices/' . $id);
        }
        db()->transaction(function (Database $d) use ($id, $item, $desc, $amount, $disc) {
            $d->insert('invoice_lines', [
                'invoice_id' => $id, 'fee_item_id' => $item['id'] ?? null, 'description' => $desc, 'amount' => $amount,
                'discount' => $disc, 'discount_note' => $disc > 0 ? (mb_substr(trim((string) ($_POST['discount_note'] ?? '')), 0, 120) ?: 'Discount') : null,
            ]);
            Fees::recalc($id, $d);
        });
        audit('invoice_line_added', 'invoices', $id, ['description' => $desc, 'amount' => $amount, 'discount' => $disc]);
        flash('success', 'Line added.');
        redirect('fees/invoices/' . $id);
    }

    public function deleteLine(int $id, int $line): void
    {
        $inv = $this->invoice($id);
        $l = db()->one('SELECT * FROM invoice_lines WHERE id = ? AND invoice_id = ?', [$line, $id]) ?? $this->notFound();
        $newTotal = (float) $inv['total'] - ((float) $l['amount'] - (float) $l['discount']);
        if ((float) $l['amount'] < 0) {
            flash('error', 'A credit note line cannot be removed.');
            redirect('fees/invoices/' . $id);
        }
        if ($inv['status'] === 'cancelled' || $newTotal + 0.004 < (float) $inv['paid']) {
            flash('error', 'This line cannot be removed: the bill would become less than what has already been paid. Ask for a credit note instead.');
            redirect('fees/invoices/' . $id);
        }
        db()->transaction(function (Database $d) use ($id, $line) {
            $d->run('DELETE FROM invoice_lines WHERE id = ?', [$line]);
            Fees::recalc($id, $d);
        });
        audit('invoice_line_removed', 'invoices', $id, ['description' => $l['description'], 'amount' => $l['amount']]);
        flash('success', 'Line removed.');
        redirect('fees/invoices/' . $id);
    }

    public function cancel(int $id): void
    {
        $inv = $this->invoice($id);
        $reason = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 200);
        $paid = (int) db()->value("SELECT COUNT(*) FROM payments WHERE invoice_id = ? AND status = 'completed'", [$id]);
        if ($inv['status'] === 'cancelled' || $paid || $reason === '') {
            flash('error', $paid ? 'Reverse the payments on this bill before cancelling it.' : 'Give a reason for cancelling.');
            redirect('fees/invoices/' . $id);
        }
        db()->update('invoices', ['status' => 'cancelled', 'notes' => 'Cancelled: ' . $reason], 'id = ?', [$id]);
        audit('invoice_cancelled', 'invoices', $id, ['reason' => $reason]);
        flash('success', 'Bill ' . $inv['invoice_no'] . ' cancelled.');
        redirect('fees/invoices/' . $id);
    }

    public function smsInvoice(int $id): void
    {
        $inv = $this->invoice($id);
        if (Fees::smsInvoice($id)) {
            Notifier::process(5);
            flash('success', 'Bill sent to the parent by SMS.');
        } else {
            flash('warning', 'Nothing sent: no guardian phone number, or nothing is owed on this bill.');
        }
        redirect('fees/invoices/' . $inv['id']);
    }

    // ---------------------------------------------------------------- payments

    public function pay(int $id): void
    {
        $inv = $this->invoice($id);
        $bal = Fees::balance($inv);
        $amount = round((float) str_replace(',', '', (string) ($_POST['amount'] ?? '0')), 2);
        $method = (string) ($_POST['method'] ?? '');
        $paidOn = (string) ($_POST['paid_on'] ?? date('Y-m-d'));
        $ref = trim((string) ($_POST['reference'] ?? ''));
        $err = null;
        if ($inv['status'] === 'cancelled') {
            $err = 'This bill is cancelled.';
        } elseif ($amount <= 0 || $amount > $bal + 0.004) {
            $err = 'Enter an amount between 0.01 and the balance of ' . Fees::money($bal) . '.';
        } elseif (!isset(Fees::METHODS[$method]) || $method === 'online') {
            $err = 'Choose how the money was paid.';
        } elseif (!Validator::isDate($paidOn) || $paidOn > date('Y-m-d') || $paidOn < date('Y-m-d', strtotime('-60 days'))) {
            $err = 'Payment date must be within the last 60 days and not in the future.';
        } elseif (in_array($method, ['momo', 'bank', 'cheque'], true) && $ref === '') {
            $err = 'Enter the transaction ID, deposit slip or cheque number.';
        }
        if (!$err) {
            $err = Finance::closedError($paidOn);
        }
        if ($err) {
            flash('error', $err);
            redirect('fees/invoices/' . $id);
        }
        $pid = Fees::recordPayment($id, $amount, $method, [
            'reference' => $ref ?: null, 'paid_on' => $paidOn,
            'payer_name' => trim((string) ($_POST['payer_name'] ?? '')) ?: null,
            'payer_phone' => Admission::normalisePhone((string) ($_POST['payer_phone'] ?? '')) ?: null,
        ]);
        audit('payment_recorded', 'payments', $pid, ['invoice' => $inv['invoice_no'], 'amount' => $amount, 'method' => $method]);
        if (!empty($_POST['sms'])) {
            Fees::smsReceipt($pid);
            Notifier::process(5);
        }
        $rct = db()->value('SELECT receipt_no FROM payments WHERE id = ?', [$pid]);
        flash('success', Fees::money($amount) . ' received. Receipt ' . $rct . '.');
        redirect('fees/invoices/' . $id, ['receipt' => $pid]);
    }

    public function receipt(int $id): void
    {
        $pay = db()->one(
            'SELECT pay.*, i.invoice_no, i.title, i.total, i.paid AS inv_paid, i.status AS inv_status, i.public_key, p.first_name, p.middle_name, p.last_name, p.admission_no,
                    c.name AS class_name, u.name AS received_by_name
             FROM payments pay JOIN invoices i ON i.id = pay.invoice_id JOIN pupils p ON p.id = pay.pupil_id
             LEFT JOIN classes c ON c.id = i.class_id LEFT JOIN users u ON u.id = pay.received_by WHERE pay.id = ?',
            [$id]
        ) ?? $this->notFound();
        $fam = null;
        if (Family::ready() && ($gid = Family::guardianFor((int) $pay['pupil_id']))) {
            $kids = array_map(fn($k) => (int) $k['id'], Family::children($gid));
            $fam = ['kids' => count($kids), 'credit' => Family::credit($gid),
                'owing' => (float) db()->value("SELECT COALESCE(SUM(total - paid), 0) FROM invoices WHERE status <> 'cancelled' AND pupil_id IN (" . implode(',', $kids ?: [0]) . ')')];
        }
        $this->view('fees/receipt', ['title' => 'Receipt ' . $pay['receipt_no'], 'pay' => $pay, 'pupilBalance' => Fees::pupilBalance((int) $pay['pupil_id']),
            'guardian' => Fees::guardian((int) $pay['pupil_id']), 'fam' => $fam, 'doc' => Docs::mark('receipt', $pay['receipt_no'])], 'layouts/print');
    }

    public function reverse(int $id): void
    {
        $pay = db()->one('SELECT * FROM payments WHERE id = ?', [$id]) ?? $this->notFound();
        $reason = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 255);
        if ($pay['status'] !== 'completed' || $reason === '') {
            flash('error', 'Give a reason for reversing this payment.');
            redirect('fees/invoices/' . $pay['invoice_id']);
        }
        if ($err = Finance::closedError($pay['method'] === 'credit' ? null : $pay['paid_on'])) {
            flash('error', $err);
            redirect('fees/invoices/' . $pay['invoice_id']);
        }
        try {
            db()->transaction(function (Database $d) use ($pay, $reason) {
                if ($err = Family::onReverse($d, $pay)) {
                    throw new DomainException($err);
                }
                $d->update('payments', ['status' => 'reversed', 'reversal_reason' => $reason, 'reversed_by' => Auth::id(), 'reversed_at' => date('Y-m-d H:i:s')], 'id = ?', [$pay['id']]);
                Fees::recalc((int) $pay['invoice_id'], $d);
                Finance::voidSource($d, 'fee_payment', (int) $pay['id'], 'Receipt reversed: ' . $reason);
            });
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
            redirect('fees/invoices/' . $pay['invoice_id']);
        }
        audit('payment_reversed', 'payments', $id, ['receipt' => $pay['receipt_no'], 'amount' => $pay['amount'], 'reason' => $reason]);
        flash('success', 'Receipt ' . $pay['receipt_no'] . ' reversed.');
        redirect('fees/invoices/' . $pay['invoice_id']);
    }

    // ---------------------------------------------------------------- generate term bills

    public function generateForm(): void
    {
        $term = $this->term();
        $tid = (int) ($_GET['term'] ?? ($term['id'] ?? 0));
        $selTerm = $tid ? $this->termById($tid) : null;
        $classes = [];
        if ($selTerm) {
            $classes = db()->all(
                "SELECT c.id, c.name, l.name AS level_name, l.id AS level_id,
                        COUNT(e.id) AS pupils,
                        SUM(EXISTS(SELECT 1 FROM invoices i WHERE i.pupil_id = e.pupil_id AND i.term_id = ? AND i.kind = 'term' AND i.status <> 'cancelled')) AS billed,
                        (SELECT COALESCE(SUM(fs.amount), 0) FROM fee_structures fs JOIN fee_items f ON f.id = fs.fee_item_id
                          WHERE fs.level_id = l.id AND f.is_active = 1 AND f.frequency IN ('termly'" . ((int) $selTerm['term_no'] === 1 ? ",'yearly'" : '') . ")) AS per_pupil
                 FROM classes c JOIN levels l ON l.id = c.level_id
                 LEFT JOIN enrolments e ON e.class_id = c.id AND e.academic_year_id = ? AND e.status = 'active'
                      AND EXISTS(SELECT 1 FROM pupils p WHERE p.id = e.pupil_id AND p.status = 'active' AND p.deleted_at IS NULL)
                 WHERE c.is_active = 1 GROUP BY c.id, c.name, l.name, l.id, l.sort_order ORDER BY l.sort_order, c.name",
                [$tid, $selTerm['academic_year_id']]
            );
        }
        $this->view('fees/generate', ['title' => 'Generate term bills', 'terms' => $this->terms(), 'selTerm' => $selTerm, 'classes' => $classes]);
    }

    public function generate(): void
    {
        $term = $this->termById((int) ($_POST['term_id'] ?? 0));
        $classIds = array_map('intval', (array) ($_POST['classes'] ?? []));
        $due = (string) ($_POST['due_date'] ?? '');
        if (!$term || !$classIds || ($due !== '' && !Validator::isDate($due))) {
            flash('error', 'Choose a term, at least one class and a valid due date.');
            redirect('fees/generate');
        }
        $in = implode(',', array_fill(0, count($classIds), '?'));
        $pupils = db()->all(
            "SELECT p.id, p.first_name, e.class_id, c.level_id FROM enrolments e JOIN pupils p ON p.id = e.pupil_id JOIN classes c ON c.id = e.class_id
             WHERE e.academic_year_id = ? AND e.status = 'active' AND p.status = 'active' AND p.deleted_at IS NULL AND e.class_id IN ($in)
             ORDER BY p.admitted_on, p.id",
            array_merge([$term['academic_year_id']], $classIds)
        );
        $opts = ['sibling' => !empty($_POST['sibling']), 'due_date' => $due ?: null];
        $created = [];
        $skipped = 0;
        foreach ($pupils as $p) {
            $iid = db()->transaction(fn(Database $d) => Fees::createTermInvoice($d, $p, $term, $opts));
            if ($iid) {
                $created[] = $iid;
            } else {
                $skipped++;
            }
        }
        $sms = 0;
        if (!empty($_POST['sms'])) {
            foreach ($created as $iid) {
                $sms += Fees::smsInvoice($iid) ? 1 : 0;
            }
            Notifier::process(30);
        }
        audit('term_bills_generated', 'invoices', null, ['term' => $term['id'], 'classes' => $classIds, 'created' => count($created), 'skipped' => $skipped]);
        flash('success', count($created) . ' bill(s) created for ' . $term['name'] . ' ' . $term['year_name'] . ($skipped ? ", $skipped student(s) skipped (already billed or nothing to pay)" : '') . ($sms ? ", $sms SMS queued" : '') . '.');
        redirect('fees/invoices', ['term' => $term['id']]);
    }

    // ---------------------------------------------------------------- pupil statement

    public function statement(int $id): void
    {
        $pupil = $this->pupil($id);
        $data = $this->statementData($id);
        $tpl = isset($_GET['print']) ? 'fees/print_statement' : 'fees/statement';
        $this->view($tpl, ['title' => 'Fee statement — ' . full_name($pupil), 'pupil' => $pupil] + $data, isset($_GET['print']) ? 'layouts/print' : 'layouts/app');
    }

    public function adhocInvoice(int $id): void
    {
        $pupil = $this->pupil($id);
        $title = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 150);
        $lines = [];
        foreach ((array) ($_POST['desc'] ?? []) as $k => $desc) {
            $desc = mb_substr(trim((string) $desc), 0, 150);
            $amt = round((float) str_replace(',', '', (string) ($_POST['amt'][$k] ?? '0')), 2);
            if ($desc !== '' && $amt > 0 && $amt <= 100000) {
                $lines[] = [$desc, $amt];
            }
        }
        $due = (string) ($_POST['due_date'] ?? '');
        if ($title === '' || !$lines || ($due !== '' && !Validator::isDate($due))) {
            flash('error', 'Give the bill a title and at least one line with an amount.');
            redirect('fees/pupil/' . $id);
        }
        $pupil['class_id'] = $pupil['class_id'] ?? null;
        $iid = db()->transaction(function (Database $d) use ($pupil, $title, $lines, $due) {
            $iid = Fees::insertInvoice($d, $pupil, current_term(), 'other', $title, $due ?: null);
            foreach ($lines as [$desc, $amt]) {
                $d->insert('invoice_lines', ['invoice_id' => $iid, 'description' => $desc, 'amount' => $amt]);
            }
            Fees::recalc($iid, $d);
            Family::autoApply($d, $iid);
            return $iid;
        });
        audit('invoice_created', 'invoices', $iid, ['title' => $title]);
        if (!empty($_POST['sms'])) {
            Fees::smsInvoice($iid);
            Notifier::process(5);
        }
        flash('success', 'Bill created.');
        redirect('fees/invoices/' . $iid);
    }

    // ---------------------------------------------------------------- reports

    public function debtors(): void
    {
        $f = ['term' => (int) ($_GET['term'] ?? 0), 'class' => (int) ($_GET['class'] ?? 0), 'min' => max(0.01, (float) ($_GET['min'] ?? 1))];
        $sql = "SELECT p.id, p.admission_no, p.first_name, p.middle_name, p.last_name, c.name AS class_name,
                       SUM(i.total - i.paid) AS balance, COUNT(i.id) AS bills, MIN(i.due_date) AS oldest_due,
                       (SELECT MAX(pay.paid_on) FROM payments pay WHERE pay.pupil_id = p.id AND pay.status = 'completed') AS last_paid,
                       (SELECT g.name FROM pupil_guardian pg JOIN guardians g ON g.id = pg.guardian_id WHERE pg.pupil_id = p.id ORDER BY pg.is_primary DESC LIMIT 1) AS guardian_name,
                       (SELECT g.phone FROM pupil_guardian pg JOIN guardians g ON g.id = pg.guardian_id WHERE pg.pupil_id = p.id ORDER BY pg.is_primary DESC LIMIT 1) AS guardian_phone
                FROM invoices i JOIN pupils p ON p.id = i.pupil_id LEFT JOIN classes c ON c.id = i.class_id
                WHERE i.status IN ('unpaid','part') AND p.deleted_at IS NULL";
        $params = [];
        if ($f['term']) {
            $sql .= ' AND i.term_id = ?';
            $params[] = $f['term'];
        }
        if ($f['class']) {
            $sql .= ' AND i.class_id = ?';
            $params[] = $f['class'];
        }
        $sql .= ' GROUP BY p.id, p.admission_no, p.first_name, p.middle_name, p.last_name, c.name HAVING SUM(i.total - i.paid) >= ? ORDER BY balance DESC';
        $params[] = $f['min'];
        $rows = db()->all($sql, $params);

        if (isset($_GET['export'])) {
            $out = csv_start('fee-debtors-' . date('Y-m-d') . '.csv', ['Admission no', 'Student', 'Class', 'Balance (GHS)', 'Bills', 'Oldest due', 'Last payment', 'Guardian', 'Phone']);
            foreach ($rows as $r) {
                csv_row($out, [$r['admission_no'], full_name($r), $r['class_name'], number_format((float) $r['balance'], 2, '.', ''), $r['bills'], $r['oldest_due'], $r['last_paid'], $r['guardian_name'], $r['guardian_phone']]);
            }
            csv_end($out);
        }
        $this->view('fees/debtors', ['title' => 'Fee debtors', 'rows' => $rows, 'f' => $f]);
    }

    public function reminders(): void
    {
        $min = max(1, (float) ($_POST['min'] ?? 1));
        $class = (int) ($_POST['class'] ?? 0) ?: null;
        $n = Fees::sendReminders($min, $class);
        Notifier::process(30);
        audit('fee_reminders_sent', 'invoices', null, ['count' => $n, 'min' => $min, 'class' => $class]);
        flash($n ? 'success' : 'warning', $n ? "$n reminder SMS queued (sent outside Sabbath hours)." : 'No reminders queued (already reminded today, or no guardian phone).');
        redirect('fees/debtors', array_filter(['class' => $class, 'min' => $min]));
    }

    public function collections(): void
    {
        $term = $this->termById((int) ($_GET['term'] ?? ($this->term()['id'] ?? 0)));
        $tid = $term['id'] ?? 0;
        $sum = db()->one(
            "SELECT COUNT(*) AS bills, COALESCE(SUM(subtotal), 0) AS gross, COALESCE(SUM(discount), 0) AS discount, COALESCE(SUM(total), 0) AS billed,
                    COALESCE(SUM(paid), 0) AS paid, COALESCE(SUM(GREATEST(total - paid, 0)), 0) AS outstanding,
                    COALESCE(SUM(status = 'paid'), 0) AS n_paid, COALESCE(SUM(status = 'part'), 0) AS n_part, COALESCE(SUM(status = 'unpaid'), 0) AS n_unpaid
             FROM invoices WHERE term_id = ? AND status <> 'cancelled'",
            [$tid]
        );
        $byItem = db()->all(
            "SELECT l.description, COUNT(*) AS n, SUM(l.amount) AS gross, SUM(l.discount) AS discount FROM invoice_lines l JOIN invoices i ON i.id = l.invoice_id
             WHERE i.term_id = ? AND i.status <> 'cancelled' GROUP BY l.description ORDER BY SUM(l.amount) DESC",
            [$tid]
        );
        $byMethod = db()->all(
            "SELECT pay.method, COUNT(*) AS n, SUM(pay.amount) AS amt FROM payments pay JOIN invoices i ON i.id = pay.invoice_id
             WHERE i.term_id = ? AND pay.status = 'completed' GROUP BY pay.method ORDER BY amt DESC",
            [$tid]
        );
        $byClass = db()->all(
            "SELECT c.name, COUNT(i.id) AS bills, SUM(i.total) AS billed, SUM(i.paid) AS paid, SUM(GREATEST(i.total - i.paid, 0)) AS outstanding
             FROM invoices i JOIN classes c ON c.id = i.class_id JOIN levels lv ON lv.id = c.level_id
             WHERE i.term_id = ? AND i.status <> 'cancelled' GROUP BY c.id, c.name, lv.sort_order ORDER BY lv.sort_order, c.name",
            [$tid]
        );
        $this->view('fees/collections', ['title' => 'Collection summary', 'term' => $term, 'terms' => $this->terms(), 'sum' => $sum, 'byItem' => $byItem, 'byMethod' => $byMethod, 'byClass' => $byClass]);
    }

    public function daily(): void
    {
        $date = (string) ($_GET['date'] ?? date('Y-m-d'));
        if (!Validator::isDate($date)) {
            $date = date('Y-m-d');
        }
        $rows = db()->all(
            'SELECT pay.*, p.first_name, p.last_name, p.admission_no, i.invoice_no, u.name AS received_by_name FROM payments pay
             JOIN pupils p ON p.id = pay.pupil_id JOIN invoices i ON i.id = pay.invoice_id LEFT JOIN users u ON u.id = pay.received_by
             WHERE pay.paid_on = ? AND pay.method <> \'credit\' ORDER BY pay.id',
            [$date]
        );
        if (Family::ready()) { // money paid ahead (kept as family credit) is cash received today too
            foreach (db()->all(
                "SELECT fc.receipt_no, g.name AS first_name, '' AS last_name, 'family' AS admission_no, 'Family credit' AS invoice_no, fc.method, fc.reference,
                        IF(fc.status = 'posted', 'completed', 'reversed') AS status, u.name AS received_by_name, fc.amount
                 FROM family_credits fc JOIN guardians g ON g.id = fc.guardian_id LEFT JOIN users u ON u.id = fc.created_by
                 WHERE fc.kind = 'advance' AND fc.entry_date = ? ORDER BY fc.id",
                [$date]
            ) as $a) {
                $rows[] = $a;
            }
        }
        $byMethod = [];
        $byUser = [];
        $total = 0.0;
        foreach ($rows as $r) {
            if ($r['status'] !== 'completed') {
                continue;
            }
            $byMethod[$r['method']] = ($byMethod[$r['method']] ?? 0) + (float) $r['amount'];
            $who = $r['method'] === 'online' ? 'Online (gateway)' : ($r['received_by_name'] ?? '—');
            $byUser[$who] = ($byUser[$who] ?? 0) + (float) $r['amount'];
            $total += (float) $r['amount'];
        }
        $tpl = isset($_GET['print']) ? 'fees/print_daily' : 'fees/daily';
        $this->view($tpl, ['title' => 'Daily cash report ' . fmt_date($date), 'date' => $date, 'rows' => $rows, 'byMethod' => $byMethod, 'byUser' => $byUser, 'total' => $total], isset($_GET['print']) ? 'layouts/print' : 'layouts/app');
    }

    public function online(): void
    {
        [$rows, $pager] = paginate(
            'SELECT op.*, i.invoice_no, p.first_name, p.last_name, pay.receipt_no FROM online_payments op JOIN invoices i ON i.id = op.invoice_id
             JOIN pupils p ON p.id = i.pupil_id LEFT JOIN payments pay ON pay.id = op.payment_id ORDER BY op.id DESC',
            [],
            50
        );
        $this->view('fees/online', ['title' => 'Online payments', 'rows' => $rows, 'pager' => $pager]);
    }

    public function checkOnline(int $id): void
    {
        $op = db()->one('SELECT * FROM online_payments WHERE id = ?', [$id]) ?? $this->notFound();
        $r = PaymentGateway::settle($op['reference']);
        flash($r === 'success' ? 'success' : ($r === 'failed' ? 'error' : 'warning'), [
            'success' => 'Payment confirmed and credited.', 'failed' => 'The gateway says this payment did not go through.',
        ][$r] ?? 'Still waiting for the gateway to confirm this payment.');
        back('fees/online');
    }

    // ---------------------------------------------------------------- settings

    public function settings(): void
    {
        $this->view('fees/settings', ['title' => 'Fee settings & online payment', 'gateway' => PaymentGateway::active()]);
    }

    public function saveSettings(): void
    {
        $gw = (string) ($_POST['pay_gateway'] ?? 'none');
        if (!isset(PaymentGateway::GATEWAYS[$gw])) {
            $gw = 'none';
        }
        $public = trim((string) ($_POST['public_url'] ?? ''));
        if ($public !== '' && !preg_match('#^https?://[a-z0-9.-]+(:[0-9]+)?(/[A-Za-z0-9._~/-]*)?$#i', $public)) {
            flash('error', 'Public web address must look like https://sms.yourschool.edu.gh');
            redirect('fees/settings');
        }
        if ($gw === 'paystack' && setting('paystack_secret') === '' && trim((string) ($_POST['paystack_secret'] ?? '')) === '') {
            flash('error', 'Enter the Paystack secret key to switch Paystack on.');
            redirect('fees/settings');
        }
        if ($gw === 'hubtel' && (trim((string) ($_POST['hubtel_merchant'] ?? '')) === '' || (setting('hubtel_api_key') === '' && trim((string) ($_POST['hubtel_api_key'] ?? '')) === ''))) {
            flash('error', 'Enter the Hubtel API ID, API key and merchant account number to switch Hubtel on.');
            redirect('fees/settings');
        }
        setting_set('pay_gateway', $gw);
        setting_set('public_url', rtrim($public, '/'));
        foreach (['hubtel_api_id', 'hubtel_merchant', 'bursar_momo_number'] as $k) {
            setting_set($k, mb_substr(trim((string) ($_POST[$k] ?? '')), 0, 80));
        }
        setting_set('bank_details', mb_substr(trim((string) ($_POST['bank_details'] ?? '')), 0, 300));
        // Secrets: a blank box keeps the saved value
        foreach (['paystack_secret', 'hubtel_api_key'] as $k) {
            $v = trim((string) ($_POST[$k] ?? ''));
            if ($v !== '') {
                setting_set($k, mb_substr($v, 0, 200));
            }
        }
        setting_set('fee_due_days', (string) max(0, min(120, (int) ($_POST['fee_due_days'] ?? 21))));
        setting_set('fee_auto_reminders', isset($_POST['fee_auto_reminders']) ? '1' : '0');
        setting_set('fee_show_on_report', isset($_POST['fee_show_on_report']) ? '1' : '0');
        setting_set('fee_stage_reminders', isset($_POST['fee_stage_reminders']) ? '1' : '0');
        $days = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) ($_POST['fee_remind_days'] ?? '7,14,30'))), fn($d) => $d > 0 && $d <= 365)));
        sort($days);
        setting_set('fee_remind_days', implode(',', array_slice($days ?: [7, 14, 30], 0, 3)));
        setting_set('fee_gateway_pct', (string) max(0, min(10, round((float) ($_POST['fee_gateway_pct'] ?? 0), 2))));
        setting_set('fee_gateway_payer', ($_POST['fee_gateway_payer'] ?? '') === 'parent' ? 'parent' : 'school');
        setting_set('finance_large_amount', (string) max(0, min(10000000, (int) ($_POST['finance_large_amount'] ?? 1000))));
        audit('fee_settings_saved', 'settings', null, ['gateway' => $gw]);
        flash('success', 'Fee settings saved.');
        redirect('fees/settings');
    }

    // ---------------------------------------------------------------- helpers

    /** Statement data for one pupil (also used by the parent pay page). */
    private function statementData(int $pupilId): array
    {
        $invoices = db()->all('SELECT i.*, c.name AS class_name FROM invoices i LEFT JOIN classes c ON c.id = i.class_id WHERE i.pupil_id = ? ORDER BY i.issue_date, i.id', [$pupilId]);
        $payments = db()->all('SELECT pay.*, i.invoice_no FROM payments pay JOIN invoices i ON i.id = pay.invoice_id WHERE pay.pupil_id = ? ORDER BY pay.paid_on, pay.id', [$pupilId]);
        // Running ledger: bills are debits, completed payments credits
        $ledger = [];
        foreach ($invoices as $i) {
            if ($i['status'] !== 'cancelled') {
                $ledger[] = ['date' => $i['issue_date'], 'sort' => 'a' . $i['id'], 'text' => $i['title'] . ' (' . $i['invoice_no'] . ')', 'debit' => (float) $i['total'], 'credit' => 0.0, 'link' => 'fees/invoices/' . $i['id']];
            }
        }
        foreach ($payments as $p) {
            if ($p['status'] === 'completed') {
                $ledger[] = ['date' => $p['paid_on'], 'sort' => 'b' . $p['id'], 'text' => 'Payment ' . $p['receipt_no'] . ' — ' . (Fees::PAID_BY[$p['method']] ?? $p['method']), 'debit' => 0.0, 'credit' => (float) $p['amount'], 'link' => 'fees/receipts/' . $p['id']];
            }
        }
        usort($ledger, fn($a, $b) => [$a['date'], $a['sort']] <=> [$b['date'], $b['sort']]);
        $run = 0.0;
        foreach ($ledger as &$l) {
            $run += $l['debit'] - $l['credit'];
            $l['balance'] = $run;
        }
        unset($l);
        return [
            'invoices' => $invoices, 'payments' => $payments, 'ledger' => $ledger, 'balance' => Fees::pupilBalance($pupilId),
            'discounts' => db()->all('SELECT * FROM discounts WHERE is_active = 1 AND is_sibling = 0 ORDER BY name'),
            'myDiscounts' => array_map('intval', array_column(db()->all('SELECT discount_id FROM pupil_discounts WHERE pupil_id = ?', [$pupilId]), 'discount_id')),
            'siblingDiscount' => Fees::discountFor($pupilId, true),
            'guardian' => Fees::guardian($pupilId),
        ];
    }


    private function term(): ?array
    {
        return current_term();
    }

    private function termById(int $id): ?array
    {
        return $id ? db()->one('SELECT t.*, y.name AS year_name FROM terms t JOIN academic_years y ON y.id = t.academic_year_id WHERE t.id = ?', [$id]) : null;
    }

    private function terms(): array
    {
        return db()->all('SELECT t.id, t.name, t.term_no, y.name AS year_name FROM terms t JOIN academic_years y ON y.id = t.academic_year_id ORDER BY y.start_date DESC, t.term_no DESC');
    }

    private function invoice(int $id): array
    {
        return db()->one(
            'SELECT i.*, p.first_name, p.middle_name, p.last_name, p.admission_no, p.status AS pupil_status, c.name AS class_name,
                    t.name AS term_name, y.name AS year_name, u.name AS created_by_name
             FROM invoices i JOIN pupils p ON p.id = i.pupil_id LEFT JOIN classes c ON c.id = i.class_id
             LEFT JOIN terms t ON t.id = i.term_id LEFT JOIN academic_years y ON y.id = t.academic_year_id
             LEFT JOIN users u ON u.id = i.created_by WHERE i.id = ?',
            [$id]
        ) ?? $this->notFound();
    }

    private function pupil(int $id): array
    {
        $yid = current_year()['id'] ?? 0;
        return db()->one(
            'SELECT p.*, c.id AS class_id, c.name AS class_name, c.level_id FROM pupils p
             LEFT JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? LEFT JOIN classes c ON c.id = e.class_id
             WHERE p.id = ? AND p.deleted_at IS NULL',
            [$yid, $id]
        ) ?? $this->notFound();
    }
}
