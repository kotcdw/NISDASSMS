<?php
declare(strict_types=1);

/**
 * Bank reconciliation: each month, tick the cash book entries that appear on the bank (or MoMo) statement,
 * enter the statement's closing balance, and check that
 *   statement balance + money in not yet on the statement − money out not yet on the statement = cash book balance.
 */
class ReconcileController extends Controller
{
    public function index(): void
    {
        $this->needsTable('fin_reconciliations');
        $accounts = db()->all("SELECT * FROM fin_accounts WHERE is_active = 1 AND kind IN ('bank','momo','online') ORDER BY sort_order, name");
        foreach ($accounts as &$a) {
            $a['last'] = db()->one("SELECT * FROM fin_reconciliations WHERE account_id = ? AND status = 'completed' ORDER BY period_end DESC LIMIT 1", [$a['id']]);
            $a['draft'] = db()->one("SELECT * FROM fin_reconciliations WHERE account_id = ? AND status = 'draft' ORDER BY period_end DESC LIMIT 1", [$a['id']]);
            $a['balance'] = Finance::balance((int) $a['id']);
            $a['next'] = $a['draft']['period_end'] ?? self::nextEnd($a['last']['period_end'] ?? null);
        }
        unset($a);
        $this->view('finance/reconcile_index', [
            'title' => 'Bank reconciliation', 'accounts' => $accounts,
            'history' => db()->all('SELECT r.*, a.name AS account_name, u.name AS completed_name FROM fin_reconciliations r JOIN fin_accounts a ON a.id = r.account_id LEFT JOIN users u ON u.id = r.completed_by ORDER BY r.period_end DESC, a.sort_order LIMIT 60'),
        ]);
    }

    /** Month end to reconcile next: the month after the last one done, or last month. */
    private static function nextEnd(?string $last): string
    {
        $prev = date('Y-m-t', strtotime('first day of last month'));
        if (!$last) {
            return $prev;
        }
        $n = date('Y-m-t', strtotime($last . ' +1 day'));
        return $n > date('Y-m-d') ? $prev : $n;
    }

    public function show(int $id): void
    {
        $this->needsTable('fin_reconciliations');
        $acct = db()->one("SELECT * FROM fin_accounts WHERE id = ? AND kind IN ('bank','momo','online')", [$id]) ?? $this->notFound();
        $last = db()->one("SELECT * FROM fin_reconciliations WHERE account_id = ? AND status = 'completed' ORDER BY period_end DESC LIMIT 1", [$id]);
        $end = (string) ($_GET['end'] ?? '');
        if (!Validator::isDate($end)) {
            $d = db()->value("SELECT period_end FROM fin_reconciliations WHERE account_id = ? AND status = 'draft' ORDER BY period_end DESC LIMIT 1", [$id]);
            $end = $d ?: self::nextEnd($last['period_end'] ?? null);
        }
        if ($last && $end <= $last['period_end']) {
            flash('error', $acct['name'] . ' is already reconciled up to ' . fmt_date($last['period_end']) . '.');
            $next = date('Y-m-t', strtotime($last['period_end'] . ' +1 day'));
            $next < date('Y-m-d') ? redirect('finance/reconcile/' . $id, ['end' => $next]) : redirect('finance/reconcile');
        }
        $rec = db()->one('SELECT * FROM fin_reconciliations WHERE account_id = ? AND period_end = ?', [$id, $end]);
        $items = self::items($id, $end);
        $this->view('finance/reconcile', [
            'title' => 'Reconcile ' . $acct['name'], 'acct' => $acct, 'end' => $end, 'rec' => $rec, 'last' => $last, 'items' => $items,
            'book' => Finance::balance($id, $end),
        ]);
    }

    /** Entries of the account up to the month end that are not yet on a completed reconciliation. */
    private static function items(int $acct, string $end): array
    {
        $rows = db()->all(
            "SELECT t.*, c.name AS category_name FROM fin_transactions t LEFT JOIN fin_categories c ON c.id = t.category_id
             WHERE t.status = 'posted' AND t.recon_id IS NULL AND t.txn_date <= ? AND (t.account_id = ? OR t.to_account_id = ?)
             ORDER BY t.txn_date, t.id",
            [$end, $acct, $acct]
        );
        foreach ($rows as &$r) {
            $r['effect'] = Finance::effect($r, $acct);
        }
        unset($r);
        return $rows;
    }

    public function save(int $id): void
    {
        $this->needsTable('fin_reconciliations');
        $acct = db()->one("SELECT * FROM fin_accounts WHERE id = ? AND kind IN ('bank','momo','online')", [$id]) ?? $this->notFound();
        $end = (string) ($_POST['end'] ?? '');
        $stmt = round((float) str_replace(',', '', (string) ($_POST['statement_balance'] ?? '0')), 2);
        if (!Validator::isDate($end) || $end > date('Y-m-d')) {
            flash('error', 'Choose a month end that has passed.');
            redirect('finance/reconcile/' . $id);
        }
        $ticked = array_map('intval', (array) ($_POST['cleared'] ?? []));
        $items = self::items($id, $end);
        $complete = ($_POST['action'] ?? '') === 'complete';
        $unIn = 0.0;
        $unOut = 0.0;
        foreach ($items as $t) {
            if (!in_array((int) $t['id'], $ticked, true)) {
                $t['effect'] > 0 ? $unIn += $t['effect'] : $unOut -= $t['effect'];
            }
        }
        $book = Finance::balance($id, $end);
        $diff = round($book - ($stmt + $unIn - $unOut), 2);
        if ($complete && abs($diff) > 0.004) {
            $complete = false;
            flash('error', 'Not balanced yet: the difference is ' . Fees::money($diff) . '. Tick the entries that are on the statement, and add any bank charges or interest missing from the cash book.');
        }
        db()->transaction(function (Database $d) use ($id, $end, $stmt, $book, $items, $ticked, $complete) {
            $rid = $d->value('SELECT id FROM fin_reconciliations WHERE account_id = ? AND period_end = ?', [$id, $end]);
            $row = ['statement_balance' => $stmt, 'book_balance' => $book, 'notes' => mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 255) ?: null];
            if ($rid) {
                $d->update('fin_reconciliations', $row, 'id = ?', [$rid]);
            } else {
                $rid = $d->insert('fin_reconciliations', $row + ['account_id' => $id, 'period_end' => $end, 'prepared_by' => Auth::id()]);
            }
            foreach ($items as $t) {
                $on = in_array((int) $t['id'], $ticked, true);
                $d->update('fin_transactions', ['cleared_on' => $on ? $end : null, 'recon_id' => $on && $complete ? $rid : null], 'id = ?', [$t['id']]);
            }
            if ($complete) {
                $d->update('fin_reconciliations', ['status' => 'completed', 'completed_by' => Auth::id(), 'completed_at' => date('Y-m-d H:i:s')], 'id = ?', [$rid]);
            }
        });
        audit($complete ? 'bank_reconciled' : 'bank_recon_saved', 'fin_accounts', $id, ['end' => $end, 'statement' => $stmt, 'difference' => $diff]);
        if ($complete) {
            flash('success', $acct['name'] . ' reconciled to ' . fmt_date($end) . '.');
            redirect('finance/reconcile');
        }
        if (abs($diff) <= 0.004 || ($_POST['action'] ?? '') !== 'complete') {
            flash('success', 'Saved. Difference: ' . Fees::money($diff) . '.');
        }
        redirect('finance/reconcile/' . $id, ['end' => $end]);
    }

    /** Undo the latest completed reconciliation of an account (to correct it). */
    public function reopen(int $rid): void
    {
        $this->needsTable('fin_reconciliations');
        $r = db()->one('SELECT * FROM fin_reconciliations WHERE id = ?', [$rid]) ?? $this->notFound();
        $latest = db()->value("SELECT id FROM fin_reconciliations WHERE account_id = ? AND status = 'completed' ORDER BY period_end DESC LIMIT 1", [$r['account_id']]);
        if ($r['status'] !== 'completed' || (int) $latest !== $rid) {
            flash('error', 'Only the latest completed reconciliation of an account can be reopened.');
            redirect('finance/reconcile');
        }
        if ($err = Finance::closedError($r['period_end'])) {
            flash('error', $err);
            redirect('finance/reconcile');
        }
        db()->transaction(function (Database $d) use ($rid) {
            $d->run('UPDATE fin_transactions SET recon_id = NULL WHERE recon_id = ?', [$rid]);
            $d->update('fin_reconciliations', ['status' => 'draft', 'completed_by' => null, 'completed_at' => null], 'id = ?', [$rid]);
        });
        audit('bank_recon_reopened', 'fin_reconciliations', $rid, []);
        flash('success', 'Reconciliation reopened.');
        redirect('finance/reconcile/' . $r['account_id'], ['end' => $r['period_end']]);
    }

    public function report(int $rid): void
    {
        $this->needsTable('fin_reconciliations');
        $r = db()->one('SELECT r.*, a.name AS account_name, a.account_no, p.name AS prepared_name, c.name AS completed_name FROM fin_reconciliations r JOIN fin_accounts a ON a.id = r.account_id LEFT JOIN users p ON p.id = r.prepared_by LEFT JOIN users c ON c.id = r.completed_by WHERE r.id = ?', [$rid]) ?? $this->notFound();
        // entries not on the statement at that month end: not cleared by then (or cleared on a later reconciliation)
        $out = db()->all(
            "SELECT t.* FROM fin_transactions t WHERE t.status = 'posted' AND t.txn_date <= ? AND (t.account_id = ? OR t.to_account_id = ?)
               AND (t.recon_id IS NULL OR EXISTS (SELECT 1 FROM fin_reconciliations x WHERE x.id = t.recon_id AND x.period_end > ?))
             ORDER BY t.txn_date, t.id",
            [$r['period_end'], $r['account_id'], $r['account_id'], $r['period_end']]
        );
        foreach ($out as &$t) {
            $t['effect'] = Finance::effect($t, (int) $r['account_id']);
        }
        unset($t);
        $this->view('finance/print_reconcile', ['title' => 'Bank reconciliation ' . $r['account_name'] . ' ' . fmt_date($r['period_end']), 'r' => $r, 'out' => $out], 'layouts/print');
    }
}
