<?php
declare(strict_types=1);

/** School library: book catalogue, lending to pupils and staff, returns, overdue reminders. */
class LibraryController extends Controller
{
    public const CATEGORIES = ['Story book / reader', 'Textbook', 'Reference', 'Bible & devotional', 'Science', 'Ghanaian languages', 'Other'];

    /** Built-in categories plus any the school added ("+ Add new category"). */
    public static function categories(): array
    {
        $extra = array_filter(array_map('trim', explode(',', setting('library_categories', ''))));
        return array_values(array_unique(array_merge(array_slice(self::CATEGORIES, 0, -1), $extra, ['Other'])));
    }

    public function index(): void
    {
        $stats = db()->one(
            "SELECT (SELECT COUNT(*) FROM library_books WHERE is_active = 1) AS titles,
                    (SELECT COALESCE(SUM(copies - copies_lost), 0) FROM library_books WHERE is_active = 1) AS copies,
                    (SELECT COUNT(*) FROM library_loans WHERE status = 'out') AS out_now,
                    (SELECT COUNT(*) FROM library_loans WHERE status = 'out' AND due_on < CURDATE()) AS overdue"
        );
        $loans = $this->loanQuery("l.status = 'out'", [], 'l.due_on, l.id', 200);
        $this->view('library/index', ['title' => 'Library', 'stats' => $stats, 'loans' => $loans, 'staffUsers' => $this->staffUsers()]);
    }

    public function books(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $cat = (string) ($_GET['category'] ?? '');
        $sql = "SELECT b.*, (SELECT COUNT(*) FROM library_loans l WHERE l.book_id = b.id AND l.status = 'out') AS out_now FROM library_books b WHERE b.is_active = 1";
        $p = [];
        if ($q !== '') {
            $sql .= ' AND (b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ? OR b.shelf LIKE ?)';
            array_push($p, "%$q%", "%$q%", "%$q%", "%$q%");
        }
        if ($cat !== '') {
            $sql .= ' AND b.category = ?';
            $p[] = $cat;
        }
        [$rows, $pager] = paginate($sql . ' ORDER BY b.title', $p, 40);
        $this->view('library/books', ['title' => 'Library books', 'rows' => $rows, 'pager' => $pager]);
    }

    public function create(): void
    {
        $this->view('library/book_form', ['title' => 'Add a book', 'b' => null, 'history' => []]);
    }

    public function store(): void
    {
        $data = $this->bookInput('library/books/create');
        $id = db()->insert('library_books', $data);
        audit('book_added', 'library_books', $id, ['title' => $data['title'], 'copies' => $data['copies']]);
        flash('success', '"' . $data['title'] . '" added to the library.');
        redirect(!empty($_POST['another']) ? 'library/books/create' : 'library/books/' . $id);
    }

    public function show(int $id): void
    {
        $b = db()->one('SELECT * FROM library_books WHERE id = ?', [$id]) ?? $this->notFound();
        $history = $this->loanQuery('l.book_id = ?', [$id], 'l.issued_on DESC, l.id DESC', 50);
        $this->view('library/book_form', ['title' => $b['title'], 'b' => $b, 'history' => $history]);
    }

    public function update(int $id): void
    {
        $b = db()->one('SELECT * FROM library_books WHERE id = ?', [$id]) ?? $this->notFound();
        $data = $this->bookInput('library/books/' . $id);
        $out = (int) db()->value("SELECT COUNT(*) FROM library_loans WHERE book_id = ? AND status = 'out'", [$id]);
        if ($data['copies'] - (int) $b['copies_lost'] < $out) {
            flash('error', "$out copies are on loan — the number of copies cannot be lower than that.");
            redirect('library/books/' . $id);
        }
        $data['is_active'] = empty($_POST['withdrawn']) ? 1 : 0;
        if (!$data['is_active'] && $out) {
            flash('error', 'Collect the copies on loan before removing the book from the catalogue.');
            redirect('library/books/' . $id);
        }
        db()->update('library_books', $data, 'id = ?', [$id]);
        audit('book_updated', 'library_books', $id);
        flash('success', 'Book details saved.');
        redirect($data['is_active'] ? 'library/books/' . $id : 'library/books');
    }

    public function issue(): void
    {
        $bookId = (int) ($_POST['book_id'] ?? 0);
        $adm = strtolower(trim((string) ($_POST['admission_no'] ?? '')));
        $staffId = (int) ($_POST['staff_user_id'] ?? 0);
        $days = max(1, min(60, (int) ($_POST['days'] ?? setting('library_loan_days', '14'))));
        $back = (string) ($_POST['back'] ?? 'library');
        $back = in_array($back, ['library', 'library/books/' . $bookId], true) ? $back : 'library';

        $b = db()->one('SELECT * FROM library_books WHERE id = ? AND is_active = 1', [$bookId]);
        if (!$b) {
            flash('error', 'Choose a book from the catalogue.');
            redirect($back);
        }
        $pupil = null;
        if ($adm !== '') {
            $pupil = db()->one("SELECT id, first_name, last_name FROM pupils WHERE LOWER(admission_no) = ? AND status = 'active' AND deleted_at IS NULL", [$adm]);
            if (!$pupil) {
                flash('error', 'No active student has admission number "' . $adm . '".');
                redirect($back);
            }
        } elseif (!$staffId || !array_filter($this->staffUsers(), fn($u) => (int) $u['id'] === $staffId)) {
            flash('error', 'Enter the student\'s admission number or choose a staff member.');
            redirect($back);
        }
        $result = db()->transaction(function (Database $d) use ($b, $pupil, $staffId, $days) {
            $d->one('SELECT id FROM library_books WHERE id = ? FOR UPDATE', [$b['id']]);
            $out = (int) $d->value("SELECT COUNT(*) FROM library_loans WHERE book_id = ? AND status = 'out'", [$b['id']]);
            if ($out >= (int) $b['copies'] - (int) $b['copies_lost']) {
                return 'none';
            }
            if ($pupil) {
                $has = (int) $d->value("SELECT COUNT(*) FROM library_loans WHERE pupil_id = ? AND status = 'out'", [$pupil['id']]);
                if ($has >= max(1, (int) setting('library_max_loans', '2'))) {
                    return 'limit';
                }
                if ($d->value("SELECT COUNT(*) FROM library_loans WHERE pupil_id = ? AND status = 'out' AND due_on < CURDATE()", [$pupil['id']])) {
                    return 'overdue';
                }
            }
            return $d->insert('library_loans', [
                'book_id' => $b['id'], 'pupil_id' => $pupil['id'] ?? null, 'staff_user_id' => $pupil ? null : $staffId,
                'issued_on' => date('Y-m-d'), 'due_on' => date('Y-m-d', strtotime("+$days days")), 'issued_by' => Auth::id(),
            ]);
        });
        $msg = ['none' => 'All copies of this book are out.', 'limit' => 'This student already has the most books allowed (' . setting('library_max_loans', '2') . '). They must return one first.', 'overdue' => 'This student has an overdue book. It must be returned first.'];
        if (is_string($result)) {
            flash('error', $msg[$result]);
            redirect($back);
        }
        audit('book_issued', 'library_loans', (int) $result, ['book' => $b['id']]);
        flash('success', '"' . $b['title'] . '" lent to ' . ($pupil ? $pupil['first_name'] . ' ' . $pupil['last_name'] : 'staff member') . ' — due back ' . date('D j M', strtotime("+$days days")) . '.');
        redirect($back);
    }

    public function returnLoan(int $id): void
    {
        $l = $this->openLoan($id);
        db()->update('library_loans', ['status' => 'returned', 'returned_on' => date('Y-m-d'), 'received_by' => Auth::id()], "id = ? AND status = 'out'", [$id]);
        audit('book_returned', 'library_loans', $id);
        flash('success', '"' . $l['title'] . '" returned' . ($l['due_on'] < date('Y-m-d') ? ' (' . (int) ((strtotime(date('Y-m-d')) - strtotime($l['due_on'])) / 86400) . ' days late)' : '') . '.');
        back('library');
    }

    public function renew(int $id): void
    {
        $l = $this->openLoan($id);
        $days = max(1, (int) setting('library_loan_days', '14'));
        $due = date('Y-m-d', strtotime("+$days days"));
        db()->update('library_loans', ['due_on' => $due], 'id = ?', [$id]);
        audit('book_renewed', 'library_loans', $id, ['due' => $due]);
        flash('success', '"' . $l['title'] . '" renewed until ' . date('D j M', strtotime($due)) . '.');
        back('library');
    }

    public function lost(int $id): void
    {
        $l = $this->openLoan($id);
        db()->transaction(function (Database $d) use ($id, $l) {
            $d->update('library_loans', ['status' => 'lost', 'returned_on' => date('Y-m-d'), 'received_by' => Auth::id(), 'note' => mb_substr((string) ($_POST['note'] ?? 'Reported lost'), 0, 160)], "id = ? AND status = 'out'", [$id]);
            $d->run('UPDATE library_books SET copies_lost = copies_lost + 1 WHERE id = ?', [$l['book_id']]);
        });
        audit('book_lost', 'library_loans', $id);
        flash('success', 'Marked as lost. If the family should pay for it, raise a bill under Fees.');
        back('library');
    }

    /** Overdue SMS to the guardians of pupils with late books (one per loan per day). */
    public function remind(): void
    {
        $sent = 0;
        foreach (db()->all(
            "SELECT l.id, l.due_on, b.title, p.id AS pupil_id, p.first_name, p.last_name FROM library_loans l JOIN library_books b ON b.id = l.book_id
             JOIN pupils p ON p.id = l.pupil_id WHERE l.status = 'out' AND l.due_on < CURDATE()"
        ) as $r) {
            $g = Fees::guardian((int) $r['pupil_id']);
            if (!$g || db()->value("SELECT 1 FROM notification_outbox WHERE event_key = 'library_overdue' AND entity = 'library_loans' AND entity_id = ? AND created_at > (NOW() - INTERVAL 6 DAY) AND status <> 'cancelled'", [$r['id']])) {
                continue;
            }
            $body = Notifier::render('library_overdue', ['guardian' => $g['name'], 'pupil' => $r['first_name'], 'title' => mb_strimwidth($r['title'], 0, 50, '…'), 'due' => date('j M', strtotime($r['due_on']))]);
            if ($body && Notifier::sms($g['phone'], $body, ['name' => $g['name'], 'event' => 'library_overdue', 'entity' => 'library_loans', 'entity_id' => $r['id']])) {
                $sent++;
            }
        }
        audit('library_reminders', 'library_loans', null, ['sent' => $sent]);
        flash('success', $sent ? "$sent reminder SMS queued (each family is reminded at most once a week per book)." : 'No reminders to send — either nothing is overdue or families were reminded in the last week.');
        redirect('library');
    }

    /** Loans list for printing (class teachers chase overdue books). */
    public function report(): void
    {
        $overdue = ($_GET['show'] ?? 'overdue') === 'overdue';
        $loans = $this->loanQuery($overdue ? "l.status = 'out' AND l.due_on < CURDATE()" : "l.status = 'out'", [], 'class_name, borrower', 1000);
        $this->view('library/report', ['title' => $overdue ? 'Overdue library books' : 'Library books on loan', 'loans' => $loans, 'overdue' => $overdue], 'layouts/print');
    }

    // ------------------------------------------------------------------ helpers

    private function loanQuery(string $where, array $p, string $order, int $limit): array
    {
        $yid = current_year()['id'] ?? 0;
        return db()->all(
            "SELECT l.*, b.title, b.author, COALESCE(CONCAT(p.first_name, ' ', p.last_name), u.name) AS borrower, p.admission_no, c.name AS class_name
             FROM library_loans l JOIN library_books b ON b.id = l.book_id
             LEFT JOIN pupils p ON p.id = l.pupil_id LEFT JOIN users u ON u.id = l.staff_user_id
             LEFT JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? LEFT JOIN classes c ON c.id = e.class_id
             WHERE $where ORDER BY $order LIMIT $limit",
            array_merge([$yid], $p)
        );
    }

    private function openLoan(int $id): array
    {
        $l = db()->one("SELECT l.*, b.title FROM library_loans l JOIN library_books b ON b.id = l.book_id WHERE l.id = ? AND l.status = 'out'", [$id]);
        if (!$l) {
            flash('error', 'That loan is already closed.');
            redirect('library');
        }
        return $l;
    }

    private function staffUsers(): array
    {
        return db()->all("SELECT id, name FROM users WHERE is_active = 1 AND role NOT IN ('parent','pupil') ORDER BY name");
    }

    private function bookInput(string $back): array
    {
        $in = $this->only(['title', 'author', 'isbn', 'publisher', 'category', 'level_band', 'shelf', 'copies']);
        $errors = Validator::check($in, ['title' => 'required|max:200', 'author' => 'max:150', 'isbn' => 'max:20', 'publisher' => 'max:120', 'shelf' => 'max:30', 'level_band' => 'max:30']);
        if (!ctype_digit((string) $in['copies']) || (int) $in['copies'] < 1 || (int) $in['copies'] > 2000) {
            $errors['copies'] = 'Enter the number of copies (1 or more).';
        }
        if ($in['category'] !== null && !in_array($in['category'], self::categories(), true)) {
            $errors['category'] = 'Choose a category.';
        }
        if ($errors) {
            with_old($_POST, $errors);
            flash('error', 'Please correct the highlighted fields.');
            redirect($back);
        }
        $in['copies'] = (int) $in['copies'];
        $in['isbn'] = $in['isbn'] ? preg_replace('/[^0-9Xx]/', '', $in['isbn']) : null;
        return $in;
    }
}
