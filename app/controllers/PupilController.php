<?php
declare(strict_types=1);

class PupilController extends Controller
{
    private const EDITABLE = [
        'first_name', 'middle_name', 'last_name', 'gender', 'date_of_birth', 'house', 'denomination', 'nationality',
        'hometown', 'address', 'allergies', 'medical_notes', 'previous_school', 'sen_category', 'sen_support',
    ];

    private const GRID_LIMIT = 5000;

    public function index(): void
    {
        $yid = current_year()['id'] ?? 0;
        $classId = (int) ($_GET['class'] ?? 0);
        $category = (string) ($_GET['category'] ?? '');
        $status = (string) ($_GET['status'] ?? 'active');
        $q = trim((string) ($_GET['q'] ?? ''));

        $sql = "SELECT p.*, c.name AS class_name,
                    (SELECT g.phone FROM pupil_guardian pg JOIN guardians g ON g.id = pg.guardian_id WHERE pg.pupil_id = p.id ORDER BY pg.is_primary DESC LIMIT 1) AS guardian_phone,
                    (SELECT g.name FROM pupil_guardian pg JOIN guardians g ON g.id = pg.guardian_id WHERE pg.pupil_id = p.id ORDER BY pg.is_primary DESC LIMIT 1) AS guardian_name
                FROM pupils p
                LEFT JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ?
                LEFT JOIN classes c ON c.id = e.class_id
                LEFT JOIN levels l ON l.id = c.level_id
                WHERE p.deleted_at IS NULL";
        $p = [$yid];
        // Least privilege: roles limited to "own classes" see only pupils in classes they teach
        [$scopeSql, $scopeP] = Auth::scopeSql('c.id');
        $sql .= " AND $scopeSql";
        $p = array_merge($p, $scopeP);
        if (in_array($status, ['active', 'withdrawn', 'transferred', 'graduated'], true)) {
            $sql .= ' AND p.status = ?';
            $p[] = $status;
        }
        if ($classId) {
            $sql .= ' AND c.id = ?';
            $p[] = $classId;
        }
        if (in_array($category, ['creche', 'kg', 'primary', 'jhs'], true)) {
            $sql .= ' AND l.category = ?';
            $p[] = $category;
        }
        if ($q !== '') {
            $sql .= ' AND (p.admission_no LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR CONCAT_WS(\' \', p.first_name, p.last_name) LIKE ?)';
            array_push($p, "%$q%", "%$q%", "%$q%", "%$q%");
        }
        // The whole list goes to the page (spreadsheet view: sort, filter and export happen in the browser)
        $pupils = db()->all($sql . ' ORDER BY l.sort_order, c.name, p.last_name, p.first_name LIMIT ' . self::GRID_LIMIT, $p);
        $pager = ['total' => count($pupils)];

        $this->view('pupils/index', [
            'title' => 'Students', 'pupils' => $pupils, 'pager' => $pager,
            'classes' => array_values(array_filter(db()->all('SELECT c.id, c.name FROM classes c JOIN levels l ON l.id = c.level_id WHERE c.is_active = 1 ORDER BY l.sort_order, c.name'), fn($c) => Auth::classInScope((int) $c['id']))),
            'scoped' => Auth::classScope() !== null,
            'classId' => $classId, 'category' => $category, 'status' => $status, 'q' => $q,
        ]);
    }

    /**
     * Cumulative record card (GES): one printable A4 record of a student's whole time at the school —
     * personal details, parents, health, every term's average, attendance and remarks, and BECE.
     */
    public function recordCard(int $id): void
    {
        $pupil = $this->find($id);
        $guardians = db()->all('SELECT g.name, g.phone, g.occupation, pg.relationship, pg.is_primary FROM pupil_guardian pg JOIN guardians g ON g.id = pg.guardian_id WHERE pg.pupil_id = ? ORDER BY pg.is_primary DESC, g.name', [$id]);
        $progress = Progress::pupil($id);
        $avg = [];
        foreach ($progress['terms'] as $t) {
            $avg[$t['id']] = $t['average'];
        }
        $terms = db()->all(
            'SELECT t.id, t.name, t.term_no, t.start_date, t.end_date, t.academic_year_id, y.name AS year_name, c.id AS class_id, c.name AS class_name
             FROM enrolments e JOIN terms t ON t.academic_year_id = e.academic_year_id JOIN academic_years y ON y.id = t.academic_year_id JOIN classes c ON c.id = e.class_id
             WHERE e.pupil_id = ? AND t.start_date <= CURDATE() ORDER BY t.start_date',
            [$id]
        );
        $rows = [];
        foreach ($terms as $t) {
            $att = Results::attendance((int) $t['class_id'], $t);
            $rem = Results::remarks((int) $t['id'], [$id])[$id] ?? null;
            $rows[] = $t + ['average' => $avg[(int) $t['id']] ?? null, 'present' => $att[$id] ?? null, 'days' => $att['_days'] ?? 0,
                'conduct' => $rem['conduct'] ?? null, 'remark' => $rem['teacher_remark'] ?? null, 'promotion' => $rem['promotion'] ?? null];
        }
        $bece = db()->one('SELECT b.*, y.name AS year_name FROM bece_candidates b LEFT JOIN academic_years y ON y.id = b.academic_year_id WHERE b.pupil_id = ? ORDER BY b.id DESC LIMIT 1', [$id]);
        $health = db()->one('SELECT * FROM pupil_health WHERE pupil_id = ?', [$id]);
        audit('record_card_viewed', 'pupils', $id);
        $this->view('pupils/record_card', ['title' => 'Cumulative record — ' . $pupil['first_name'] . ' ' . $pupil['last_name'], 'pupil' => $pupil, 'guardians' => $guardians,
            'rows' => $rows, 'subjects' => $progress['subjects'], 'pterms' => $progress['terms'], 'bece' => $bece, 'health' => $health], 'layouts/print');
    }

    public function show(int $id): void
    {
        $pupil = $this->find($id);
        $guardians = db()->all('SELECT g.*, pg.relationship, pg.is_primary FROM pupil_guardian pg JOIN guardians g ON g.id = pg.guardian_id WHERE pg.pupil_id = ? ORDER BY pg.is_primary DESC, g.name', [$id]);
        $siblings = db()->all(
            'SELECT DISTINCT p.id, p.admission_no, p.first_name, p.last_name, p.status FROM pupil_guardian a
             JOIN pupil_guardian b ON b.guardian_id = a.guardian_id AND b.pupil_id <> a.pupil_id
             JOIN pupils p ON p.id = b.pupil_id WHERE a.pupil_id = ? AND p.deleted_at IS NULL',
            [$id]
        );
        $enrolments = db()->all('SELECT e.*, c.name AS class_name, y.name AS year_name FROM enrolments e JOIN classes c ON c.id = e.class_id JOIN academic_years y ON y.id = e.academic_year_id WHERE e.pupil_id = ? ORDER BY y.start_date DESC', [$id]);
        $application = $pupil['application_id'] ? db()->one('SELECT id, ref_no FROM applications WHERE id = ?', [$pupil['application_id']]) : null;
        $term = current_term();
        $attendance = $term ? db()->one(
            "SELECT SUM(status IN ('present','late')) AS present, SUM(status = 'absent') AS absent, SUM(status = 'late') AS late, COUNT(*) AS marked
             FROM attendance_records WHERE pupil_id = ? AND date BETWEEN ? AND ?",
            [$id, $term['start_date'], $term['end_date']]
        ) : null;
        $recentAbs = db()->all("SELECT date, status, note FROM attendance_records WHERE pupil_id = ? AND status <> 'present' ORDER BY date DESC LIMIT 5", [$id]);
        // Average and position per term (only terms with scores)
        $results = [];
        if (can('reports.view')) {
            foreach (db()->all('SELECT t.id, t.name, y.name AS year_name, e.class_id FROM terms t JOIN academic_years y ON y.id = t.academic_year_id JOIN enrolments e ON e.academic_year_id = t.academic_year_id AND e.pupil_id = ? WHERE t.start_date <= CURDATE() ORDER BY t.start_date DESC', [$id]) as $t) {
                if (!db()->value('SELECT 1 FROM assessments a JOIN class_subjects cs ON cs.id = a.class_subject_id WHERE a.term_id = ? AND cs.class_id = ? LIMIT 1', [$t['id'], $t['class_id']])) {
                    continue;
                }
                $classRow = db()->one('SELECT * FROM classes WHERE id = ?', [$t['class_id']]);
                if (!Results::canViewClass($classRow)) {
                    continue;
                }
                $R = Results::classResults((int) $t['class_id'], (int) $t['id']);
                $results[] = ['term' => $t, 'summary' => $R['summary'][$id] ?? null, 'roll' => count($R['pupils']), 'show_position' => $R['scheme']['show_position']];
            }
        }
        $fees = can('fees.view') ? [
            'balance' => Fees::pupilBalance($id),
            'last' => db()->one("SELECT receipt_no, amount, paid_on FROM payments WHERE pupil_id = ? AND status = 'completed' ORDER BY id DESC LIMIT 1", [$id]),
        ] : null;
        $progress = can('reports.view') ? Progress::pupil($id) : null;
        $passMark = null;
        if ($progress && ($cat = db()->value('SELECT l.category FROM enrolments e JOIN classes c ON c.id = e.class_id JOIN levels l ON l.id = c.level_id WHERE e.pupil_id = ? ORDER BY e.academic_year_id DESC LIMIT 1', [$id]))) {
            $passMark = (float) Results::scheme((string) $cat)['pass_mark'];
        }
        $this->view('pupils/show', [
            'fees' => $fees, 'progress' => $progress, 'passMark' => $passMark,
            'results' => $results,
            'attendance' => $attendance, 'term' => $term, 'recentAbs' => $recentAbs,
            'title' => full_name($pupil), 'pupil' => $pupil, 'guardians' => $guardians, 'siblings' => $siblings,
            'enrolments' => $enrolments, 'application' => $application,
        ]);
    }

    public function photo(int $id): void
    {
        $pupil = $this->find($id);
        if (!$pupil['photo_path']) {
            $this->notFound();
        }
        $mime = str_ends_with($pupil['photo_path'], '.png') ? 'image/png' : 'image/jpeg';
        Upload::send($pupil['photo_path'], $mime, 'photo');
    }

    public function edit(int $id): void
    {
        $this->view('pupils/edit', ['title' => 'Edit student', 'pupil' => $this->find($id)]);
    }

    public function update(int $id): void
    {
        $this->find($id);
        $in = $this->only(self::EDITABLE);
        if (!can('pupils.sensitive')) {
            // Health notes and special needs are kept as they are by people without that permission
            unset($in['medical_notes'], $in['sen_category'], $in['sen_support']);
        }
        $this->validate($in, [
            'first_name'    => 'required|max:80',
            'middle_name'   => 'max:80',
            'last_name'     => 'required|max:80',
            'gender'        => 'required|in:male,female',
            'date_of_birth' => 'required|date|past',
            'house'         => 'max:40',
            'denomination'  => 'max:80',
            'nationality'   => 'max:60',
            'hometown'      => 'max:80',
            'address'       => 'max:255',
            'allergies'     => 'max:255',
            'medical_notes' => 'max:500',
            'sen_category'  => 'in:' . implode(',', array_keys(Admission::SEN)),
            'sen_support'   => 'max:500',
        ], "pupils/$id/edit");

        if (!empty($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            [$info, $err] = Upload::store($_FILES['photo'], 'pupils');
            if ($err || !in_array($info['mime'] ?? '', ['image/jpeg', 'image/png'], true)) {
                flash('error', $err ?: 'Photo must be a JPG or PNG image.');
                redirect("pupils/$id/edit");
            }
            $in['photo_path'] = $info['stored_name'];
        }
        db()->update('pupils', $in, 'id = ?', [$id]);
        audit('pupil_updated', 'pupils', $id);
        flash('success', 'Student record updated.');
        redirect('pupils/' . $id);
    }

    public function addGuardian(int $id): void
    {
        $this->find($id);
        $in = $this->only(['name', 'relationship', 'phone', 'phone_alt', 'email', 'occupation', 'address']);
        $errors = Validator::check($in, [
            'name'         => 'required|max:150',
            'relationship' => 'required|max:40',
            'phone'        => 'required|phone',
            'phone_alt'    => 'phone',
            'email'        => 'email|max:190',
        ]);
        if ($errors) {
            flash('error', reset($errors));
            redirect('pupils/' . $id);
        }

        $phone = Admission::normalisePhone($in['phone']);
        db()->transaction(function (Database $d) use ($id, $in, $phone) {
            $gid = (int) $d->value('SELECT id FROM guardians WHERE phone = ? LIMIT 1', [$phone]);
            if (!$gid) {
                $gid = $d->insert('guardians', [
                    'name' => $in['name'], 'phone' => $phone, 'phone_alt' => Admission::normalisePhone($in['phone_alt']),
                    'email' => $in['email'], 'occupation' => $in['occupation'], 'address' => $in['address'],
                ]);
            }
            $d->run('INSERT IGNORE INTO pupil_guardian (pupil_id, guardian_id, relationship, is_primary) VALUES (?, ?, ?, 0)', [$id, $gid, $in['relationship']]);
        });
        audit('guardian_added', 'pupils', $id, ['phone' => $phone]);
        flash('success', 'Guardian added.');
        redirect('pupils/' . $id);
    }

    public function updateGuardian(int $id, int $guardianId): void
    {
        $this->find($id);
        $guardian = db()->one(
            'SELECT g.id, g.user_id FROM pupil_guardian pg JOIN guardians g ON g.id = pg.guardian_id WHERE pg.pupil_id = ? AND g.id = ?',
            [$id, $guardianId]
        ) ?? $this->notFound();
        $in = $this->only(['name', 'relationship', 'phone', 'phone_alt', 'email', 'occupation', 'address', 'is_primary']);
        $errors = Validator::check($in, [
            'name' => 'required|max:150', 'relationship' => 'required|max:40', 'phone' => 'required|phone',
            'phone_alt' => 'phone', 'email' => 'email|max:190', 'occupation' => 'max:100', 'address' => 'max:255',
        ]);
        if ($errors) {
            flash('error', reset($errors));
            redirect('pupils/' . $id);
        }
        $in['phone'] = Admission::normalisePhone($in['phone']);
        $in['phone_alt'] = Admission::normalisePhone($in['phone_alt']) ?: null;
        $in['email'] = strtolower(trim((string) $in['email'])) ?: null;
        if (!empty($guardian['user_id']) && db()->value('SELECT id FROM users WHERE username = ? AND id <> ?', [$in['phone'], $guardian['user_id']])) {
            flash('error', 'That phone number is already being used as another login name.');
            redirect('pupils/' . $id);
        }
        $in['is_primary'] = isset($_POST['is_primary']) ? 1 : 0;
        db()->transaction(function (Database $d) use ($id, $guardianId, $guardian, $in) {
            $d->update('guardians', [
                'name' => $in['name'], 'phone' => $in['phone'], 'phone_alt' => $in['phone_alt'],
                'email' => $in['email'], 'occupation' => $in['occupation'], 'address' => $in['address'],
            ], 'id = ?', [$guardianId]);
            if (!empty($guardian['user_id'])) {
                $d->update('users', ['name' => $in['name'], 'username' => $in['phone'], 'phone' => $in['phone']], 'id = ? AND role = \'parent\'', [$guardian['user_id']]);
            }
            $d->update('pupil_guardian', ['relationship' => $in['relationship'], 'is_primary' => $in['is_primary']], 'pupil_id = ? AND guardian_id = ?', [$id, $guardianId]);
            if ($in['is_primary']) {
                $d->run('UPDATE pupil_guardian SET is_primary = 0 WHERE pupil_id = ? AND guardian_id <> ?', [$id, $guardianId]);
            }
        });
        audit('guardian_updated', 'guardians', $guardianId, ['pupil' => $id]);
        flash('success', 'Parent / guardian details updated.');
        redirect('pupils/' . $id);
    }

    public function removeGuardian(int $id, int $guardianId): void
    {
        $this->find($id);
        $guardian = db()->one(
            'SELECT g.id, pg.is_primary FROM pupil_guardian pg JOIN guardians g ON g.id = pg.guardian_id WHERE pg.pupil_id = ? AND g.id = ?',
            [$id, $guardianId]
        ) ?? $this->notFound();
        db()->transaction(function (Database $d) use ($id, $guardian) {
            $d->run('DELETE FROM pupil_guardian WHERE pupil_id = ? AND guardian_id = ?', [$id, $guardian['id']]);
            if ($guardian['is_primary']) {
                $next = $d->value('SELECT guardian_id FROM pupil_guardian WHERE pupil_id = ? ORDER BY guardian_id LIMIT 1', [$id]);
                if ($next) {
                    $d->update('pupil_guardian', ['is_primary' => 1], 'pupil_id = ? AND guardian_id = ?', [$id, $next]);
                }
            }
        });
        audit('guardian_unlinked', 'pupils', $id, ['guardian' => $guardianId]);
        flash('success', 'Parent / guardian removed from this student. Other linked students are not affected.');
        redirect('pupils/' . $id);
    }

    public function changeStatus(int $id): void
    {
        $pupil = $this->find($id);
        $status = (string) ($_POST['status'] ?? '');
        $date = (string) ($_POST['left_on'] ?? date('Y-m-d'));
        if (!in_array($status, ['active', 'withdrawn', 'transferred', 'graduated'], true) || !Validator::isDate($date)) {
            flash('error', 'Choose a valid status and date.');
            redirect('pupils/' . $id);
        }
        db()->transaction(function (Database $d) use ($id, $status, $date) {
            $d->update('pupils', ['status' => $status, 'left_on' => $status === 'active' ? null : $date], 'id = ?', [$id]);
            $yid = current_year()['id'] ?? 0;
            $d->run('UPDATE enrolments SET status = ? WHERE pupil_id = ? AND academic_year_id = ?', [$status === 'active' ? 'active' : 'left', $id, $yid]);
        });
        audit('pupil_status', 'pupils', $id, ['from' => $pupil['status'], 'to' => $status]);
        flash('success', 'Status changed to ' . $status . '.');
        redirect('pupils/' . $id);
    }

    private function find(int $id): array
    {
        if (!Auth::pupilInScope($id)) {
            audit('access_denied', 'pupils', $id, ['reason' => 'outside own classes']);
            http_response_code(403);
            $this->view('errors/403', ['title' => 'Access denied']);
            exit;
        }
        $yid = current_year()['id'] ?? 0;
        return db()->one(
            'SELECT p.*, c.name AS class_name, c.id AS class_id, u.name AS class_teacher
             FROM pupils p
             LEFT JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ?
             LEFT JOIN classes c ON c.id = e.class_id
             LEFT JOIN users u ON u.id = c.class_teacher_id
             WHERE p.id = ? AND p.deleted_at IS NULL',
            [$yid, $id]
        ) ?? $this->notFound();
    }
}
