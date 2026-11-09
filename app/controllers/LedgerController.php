<?php
declare(strict_types=1);

/** The books (double entry): chart of accounts, journal, trial balance, balance sheet and manual journals. */
class LedgerController extends Controller
{
    private function ready(): void
    {
        $this->needsTable('gl_entries');
        Ledger::sync();
    }

    private static function date(string $key, string $default): string
    {
        $v = (string) ($_GET[$key] ?? '');
        return Validator::isDate($v) ? $v : $default;
    }

    /** Plain summary: do the books balance and agree, what the school owns and owes. */
    public function index(): void
    {
        $this->ready();
        [, $td, $tc] = Ledger::trialBalance(date('Y-m-d'));
        $this->view('ledger/summary', [
            'title' => 'The books', 'bs' => Ledger::balanceSheet(date('Y-m-d')), 'controls' => Ledger::controls(),
            'balanced' => abs($td - $tc) < 0.01, 'pending' => (int) db()->value("SELECT COUNT(*) FROM gl_journals WHERE status = 'pending'"),
        ]);
    }

    public function accounts(): void
    {
        $this->ready();
        $asAt = self::date('as_at', date('Y-m-d'));
        [, $td, $tc] = Ledger::trialBalance($asAt);
        $this->view('ledger/index', [
            'title' => 'The books', 'asAt' => $asAt, 'chart' => Ledger::chart($asAt), 'controls' => Ledger::controls(),
            'balanced' => abs($td - $tc) < 0.01, 'pending' => (int) db()->value("SELECT COUNT(*) FROM gl_journals WHERE status = 'pending'"),
        ]);
    }

    public function account(int $id): void
    {
        $this->ready();
        $a = db()->one('SELECT * FROM gl_accounts WHERE id = ?', [$id]);
        if (!$a) {
            $this->notFound();
        }
        $year = current_year();
        $from = self::date('from', $year['start_date'] ?? date('Y-01-01'));
        $to = self::date('to', date('Y-m-d'));
        $sign = Ledger::debitNormal($a['type']) ? 1 : -1;
        $opening = round($sign * (float) db()->value('SELECT COALESCE(SUM(l.debit - l.credit), 0) FROM gl_lines l JOIN gl_entries e ON e.id = l.entry_id WHERE l.account_id = ? AND e.entry_date < ?', [$id, $from]), 2);
        $rows = db()->all(
            'SELECT e.*, l.debit, l.credit FROM gl_lines l JOIN gl_entries e ON e.id = l.entry_id
             WHERE l.account_id = ? AND e.entry_date BETWEEN ? AND ? ORDER BY e.entry_date, e.id',
            [$id, $from, $to]
        );
        $run = $opening;
        foreach ($rows as &$r) {
            $run = round($run + $sign * ((float) $r['debit'] - (float) $r['credit']), 2);
            $r['running'] = $run;
        }
        unset($r);
        if (isset($_GET['export'])) {
            $this->csv('account-' . $a['code'], ['Date', 'Ref', 'Details', 'Debit', 'Credit', 'Balance'], array_map(fn($r) => [$r['entry_date'], $r['ref'], $r['memo'], $r['debit'], $r['credit'], $r['running']], $rows));
        }
        $this->view('ledger/account', ['title' => $a['code'] . ' ' . $a['name'], 'a' => $a, 'from' => $from, 'to' => $to, 'opening' => $opening, 'rows' => $rows, 'closing' => $run]);
    }

    public function saveAccount(?int $id = null): void
    {
        $this->needsTable('gl_accounts');
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 120);
        $back = $id ? 'ledger/accounts/' . $id : 'ledger/accounts';
        if ($name === '') {
            flash('error', 'Give the account a name.');
            redirect($back);
        }
        if ($id) {
            $a = db()->one('SELECT * FROM gl_accounts WHERE id = ?', [$id]);
            if (!$a) {
                $this->notFound();
            }
            $upd = ['name' => $name];
            if (!$a['sys_key'] && !$a['fin_account_id'] && !$a['category_id']) {
                $upd['is_active'] = empty($_POST['is_active']) ? 0 : 1;
            }
            db()->update('gl_accounts', $upd, 'id = ?', [$id]);
            audit('gl_account_updated', 'gl_accounts', $id, $upd);
            flash('success', 'Account saved.');
            redirect($back);
        }
        $type = (string) ($_POST['type'] ?? '');
        $code = preg_replace('/\D/', '', (string) ($_POST['code'] ?? ''));
        $ranges = ['asset' => [1000, 1999], 'liability' => [2000, 2999], 'equity' => [3000, 3999], 'income' => [4000, 4999], 'expense' => [5000, 5999]];
        if (!isset($ranges[$type])) {
            flash('error', 'Choose what kind of account it is.');
            redirect($back);
        }
        if ($code === '' || (int) $code < $ranges[$type][0] || (int) $code > $ranges[$type][1]) {
            flash('error', 'The code for "' . Ledger::TYPES[$type] . '" must be a number from ' . $ranges[$type][0] . ' to ' . $ranges[$type][1] . '.');
            redirect($back);
        }
        if (db()->value('SELECT id FROM gl_accounts WHERE code = ?', [$code])) {
            flash('error', 'Code ' . $code . ' is already used.');
            redirect($back);
        }
        $nid = db()->insert('gl_accounts', ['code' => $code, 'name' => $name, 'type' => $type]);
        audit('gl_account_created', 'gl_accounts', $nid, ['code' => $code, 'name' => $name]);
        flash('success', 'Account ' . $code . ' added. Use it in a manual journal.');
        redirect('ledger/accounts');
    }

    public function journal(): void
    {
        $this->ready();
        $year = current_year();
        $f = ['from' => self::date('from', '2000-01-01'), 'to' => self::date('to', date('Y-m-d')),
            'source' => (string) ($_GET['source'] ?? ''), 'q' => trim((string) ($_GET['q'] ?? ''))];
        $w = ['e.entry_date BETWEEN ? AND ?'];
        $p = [$f['from'], $f['to']];
        if (isset(Ledger::SOURCES[$f['source']])) {
            $w[] = 'e.source = ?';
            $p[] = $f['source'];
        }
        if ($f['q'] !== '') {
            $w[] = '(e.ref LIKE ? OR e.memo LIKE ?)';
            array_push($p, '%' . $f['q'] . '%', '%' . $f['q'] . '%');
        }
        $sql = 'SELECT e.*, (SELECT SUM(debit) FROM gl_lines WHERE entry_id = e.id) AS total FROM gl_entries e WHERE ' . implode(' AND ', $w) . ' ORDER BY e.entry_date DESC, e.id DESC';
        if (isset($_GET['export'])) {
            $rows = db()->all(
                'SELECT e.entry_date, e.ref, e.source, e.memo, a.code, a.name, l.debit, l.credit FROM gl_entries e JOIN gl_lines l ON l.entry_id = e.id JOIN gl_accounts a ON a.id = l.account_id
                 WHERE ' . implode(' AND ', $w) . ' ORDER BY e.entry_date, e.id, l.id',
                $p
            );
            $this->csv('journal', ['Date', 'Ref', 'From', 'Details', 'Account code', 'Account', 'Debit', 'Credit'], array_map(fn($r) => [$r['entry_date'], $r['ref'], Ledger::SOURCES[$r['source']] ?? $r['source'], $r['memo'], $r['code'], $r['name'], $r['debit'], $r['credit']], $rows));
        }
        [$rows, $pager] = paginate($sql, $p, 40);
        $ids = array_column($rows, 'id');
        $lines = [];
        if ($ids) {
            foreach (db()->all('SELECT l.*, a.code, a.name FROM gl_lines l JOIN gl_accounts a ON a.id = l.account_id WHERE l.entry_id IN (' . implode(',', array_map('intval', $ids)) . ') ORDER BY l.debit = 0, a.code') as $l) {
                $lines[(int) $l['entry_id']][] = $l;
            }
        }
        $this->view('ledger/journal', ['title' => 'Journal', 'pager' => $pager, 'rows' => $rows, 'lines' => $lines]);
    }

    public function entry(int $id): void
    {
        $this->ready();
        $e = db()->one('SELECT e.*, u.name AS by_name FROM gl_entries e LEFT JOIN users u ON u.id = e.created_by WHERE e.id = ?', [$id]);
        if (!$e) {
            $this->notFound();
        }
        $this->view('ledger/entry', [
            'title' => 'Journal entry', 'e' => $e,
            'lines' => db()->all('SELECT l.*, a.code, a.name FROM gl_lines l JOIN gl_accounts a ON a.id = l.account_id WHERE l.entry_id = ? ORDER BY l.debit = 0, a.code', [$id]),
            'history' => db()->all('SELECT e.*, (SELECT SUM(debit) FROM gl_lines WHERE entry_id = e.id) AS total FROM gl_entries e WHERE source = ? AND source_id = ? ORDER BY id', [$e['source'], $e['source_id']]),
            'link' => Ledger::sourceLink($e['source'], (int) $e['source_id']),
        ]);
    }

    public function trialBalance(): void
    {
        $this->ready();
        $asAt = self::date('as_at', date('Y-m-d'));
        [$rows, $td, $tc] = Ledger::trialBalance($asAt);
        if (isset($_GET['export'])) {
            $this->csv('trial-balance-' . $asAt, ['Code', 'Account', 'Debit', 'Credit'], array_merge(array_map(fn($r) => [$r['code'], $r['name'], $r['tb_dr'] ?: '', $r['tb_cr'] ?: ''], $rows), [['', 'Total', $td, $tc]]));
        }
        $data = ['title' => 'Trial balance', 'asAt' => $asAt, 'rows' => $rows, 'td' => $td, 'tc' => $tc];
        isset($_GET['print']) ? $this->view('ledger/print_tb', $data, 'layouts/print') : $this->view('ledger/trial_balance', $data);
    }

    public function balanceSheet(): void
    {
        $this->ready();
        $asAt = self::date('as_at', date('Y-m-d'));
        $bs = Ledger::balanceSheet($asAt);
        if (isset($_GET['export'])) {
            $out = [];
            foreach (['asset' => 'What the school owns', 'liability' => 'What the school owes', 'equity' => 'School fund'] as $k => $label) {
                $out[] = [$label, '', ''];
                foreach ($bs['groups'][$k] as $a) {
                    $out[] = [$a['code'], $a['name'], $a['balance']];
                }
                if ($k === 'equity') {
                    $out[] = ['', 'Surplus / (deficit) to date', $bs['surplus']];
                }
                $out[] = ['', 'Total', $k === 'equity' ? round($bs['totals']['equity'] + $bs['surplus'], 2) : $bs['totals'][$k]];
            }
            $this->csv('balance-sheet-' . $asAt, ['Code', 'Account', 'GH₵'], $out);
        }
        $data = ['title' => 'Balance sheet', 'asAt' => $asAt, 'bs' => $bs];
        isset($_GET['print']) ? $this->view('ledger/print_bs', $data, 'layouts/print') : $this->view('ledger/balance_sheet', $data);
    }

    // ------------------------------------------------------------------ manual journals

    public function journals(): void
    {
        $this->ready();
        $this->view('ledger/journals', [
            'title' => 'Manual journals',
            'rows' => db()->all('SELECT j.*, u.name AS drafted_name, d.name AS decided_name FROM gl_journals j LEFT JOIN users u ON u.id = j.drafted_by LEFT JOIN users d ON d.id = j.decided_by ORDER BY j.status = \'pending\' DESC, j.id DESC'),
            'accounts' => db()->all('SELECT * FROM gl_accounts WHERE is_active = 1 ORDER BY code'),
        ]);
    }

    public function storeJournal(): void
    {
        $this->needsTable('gl_journals');
        $date = (string) ($_POST['entry_date'] ?? '');
        $memo = mb_substr(trim((string) ($_POST['memo'] ?? '')), 0, 255);
        $lines = [];
        foreach ((array) ($_POST['account'] ?? []) as $k => $acct) {
            $dr = round((float) str_replace(',', '', (string) ($_POST['debit'][$k] ?? '')), 2);
            $cr = round((float) str_replace(',', '', (string) ($_POST['credit'][$k] ?? '')), 2);
            if ((int) $acct === 0 && $dr == 0 && $cr == 0) {
                continue;
            }
            $lines[] = ['account_id' => (int) $acct, 'debit' => max(0, $dr), 'credit' => max(0, $cr), 'note' => mb_substr(trim((string) ($_POST['note'][$k] ?? '')), 0, 150) ?: null];
        }
        $td = round(array_sum(array_column($lines, 'debit')), 2);
        $tc = round(array_sum(array_column($lines, 'credit')), 2);
        $valid = array_column(db()->all('SELECT id FROM gl_accounts WHERE is_active = 1'), 'id');
        $err = null;
        if (!Validator::isDate($date) || $date > date('Y-m-d')) {
            $err = 'Enter the date of the correction (today or earlier).';
        } elseif ($closed = Finance::closedError($date)) {
            $err = $closed;
        } elseif ($memo === '') {
            $err = 'Say what the correction is for.';
        } elseif (count($lines) < 2) {
            $err = 'A journal needs at least two lines: one account debited and one credited.';
        } else {
            foreach ($lines as $l) {
                if (!in_array((string) $l['account_id'], array_map('strval', $valid), true)) {
                    $err = 'Choose an account on every line.';
                    break;
                }
                if (($l['debit'] > 0) === ($l['credit'] > 0)) {
                    $err = 'Each line takes either a debit or a credit amount (not both, not neither).';
                    break;
                }
            }
        }
        if (!$err && abs($td - $tc) >= 0.005) {
            $err = 'Debits (' . number_format($td, 2) . ') and credits (' . number_format($tc, 2) . ') must be equal.';
        }
        if ($err) {
            with_old($_POST);
            flash('error', $err);
            redirect('ledger/journals');
        }
        $id = db()->transaction(function (Database $d) use ($date, $memo, $lines, $td) {
            $id = $d->insert('gl_journals', ['jnl_no' => Fees::nextNumber($d, 'JNL'), 'entry_date' => $date, 'memo' => $memo, 'total' => $td, 'drafted_by' => Auth::id()]);
            foreach ($lines as $l) {
                $d->insert('gl_journal_lines', $l + ['journal_id' => $id]);
            }
            return $id;
        });
        audit('journal_drafted', 'gl_journals', $id, ['total' => $td]);
        Notifier::inappPermission('ledger.approve', 'Manual journal to approve', $memo . ' — ' . Fees::money($td), 'ledger/journals/' . $id);
        flash('success', 'Journal saved. It counts in the books only after it is approved.');
        redirect('ledger/journals/' . $id);
    }

    public function showJournal(int $id): void
    {
        $this->ready();
        $j = db()->one('SELECT j.*, u.name AS drafted_name, d.name AS decided_name FROM gl_journals j LEFT JOIN users u ON u.id = j.drafted_by LEFT JOIN users d ON d.id = j.decided_by WHERE j.id = ?', [$id]);
        if (!$j) {
            $this->notFound();
        }
        $this->view('ledger/journal_show', [
            'title' => $j['jnl_no'], 'j' => $j,
            'lines' => db()->all('SELECT l.*, a.code, a.name FROM gl_journal_lines l JOIN gl_accounts a ON a.id = l.account_id WHERE l.journal_id = ? ORDER BY l.id', [$id]),
            'entry' => db()->value("SELECT id FROM gl_entries WHERE source = 'jnl' AND source_id = ? ORDER BY id LIMIT 1", [$id]),
        ]);
    }

    public function decideJournal(int $id): void
    {
        $this->needsTable('gl_journals');
        $j = db()->one('SELECT * FROM gl_journals WHERE id = ?', [$id]);
        $approve = ($_POST['decision'] ?? '') === 'approve';
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 255);
        $err = null;
        if (!$j || $j['status'] !== 'pending') {
            $err = 'This journal has already been decided.';
        } elseif ((int) $j['drafted_by'] === (int) Auth::id() && Auth::role() !== 'super_admin') {
            $err = 'Someone else must approve a journal you typed.';
        } elseif (!$approve && $note === '') {
            $err = 'Say why the journal is rejected.';
        } elseif ($approve && ($closed = Finance::closedError($j['entry_date']))) {
            $err = $closed;
        }
        if ($err) {
            flash('error', $err);
            redirect('ledger/journals/' . $id);
        }
        db()->update('gl_journals', ['status' => $approve ? 'approved' : 'rejected', 'decided_by' => Auth::id(), 'decided_at' => date('Y-m-d H:i:s'), 'decision_note' => $note ?: null], 'id = ?', [$id]);
        audit($approve ? 'journal_approved' : 'journal_rejected', 'gl_journals', $id);
        Notifier::inapp([(int) $j['drafted_by']], 'Journal ' . $j['jnl_no'] . ($approve ? ' approved' : ' rejected'), $note ?: null, 'ledger/journals/' . $id);
        if ($approve) {
            Ledger::sync();
        }
        flash('success', $approve ? 'Approved — the journal is now in the books.' : 'Journal rejected.');
        redirect('ledger/journals/' . $id);
    }

    private function csv(string $name, array $head, array $rows): void
    {
        csv_download($name . '-' . date('Y-m-d') . '.csv', $head, $rows, true);
    }
}
