<?php
declare(strict_types=1);

class SetupController extends Controller
{
    private const SCHOOL_KEYS = ['school_name', 'school_motto', 'school_address', 'school_phone', 'school_email', 'admission_prefix', 'school_tin'];

    public function index(): void
    {
        $settings = [];
        foreach (db()->all('SELECT `key`, `value` FROM settings') as $r) {
            $settings[$r['key']] = $r['value'];
        }
        $this->view('setup/index', ['title' => 'School setup', 's' => $settings]);
    }

    public function saveSchool(): void
    {
        $in = $this->only(self::SCHOOL_KEYS);
        $in['online_admission_open'] = isset($_POST['online_admission_open']) ? '1' : '0';
        $in['sabbath_guard'] = isset($_POST['sabbath_guard']) ? '1' : '0';
        $this->validate($in, [
            'school_name'      => 'required|max:150',
            'school_email'     => 'email',
            'school_phone'     => 'max:40',
            'admission_prefix' => 'required|max:8',
            'school_tin'       => 'max:20',
        ], 'setup');
        $in['school_tin'] = strtoupper(preg_replace('/\s+/', '', (string) ($in['school_tin'] ?? '')));
        foreach ($in as $k => $v) {
            db()->run('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)', [$k, (string) $v]);
        }
        audit('settings_updated', 'settings', null, $in);
        flash('success', 'School details saved.');
        redirect('setup');
    }

    /** Public: school logo (used on login page, report cards and the online forms). */
    public function logo(): void
    {
        $stored = setting('school_logo');
        $path = $stored ? Upload::path($stored) : null;
        if (!$path) {
            header('Location: ' . asset('img/crest.png'));
            exit;
        }
        header('Content-Type: ' . (str_ends_with($stored, '.png') ? 'image/png' : 'image/jpeg'));
        header('Cache-Control: public, max-age=86400');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function saveLogo(): void
    {
        if (!empty($_POST['remove'])) {
            setting_set('school_logo', '');
            audit('logo_removed', 'settings');
            flash('success', 'Logo removed.');
            redirect('setup');
        }
        [$info, $err] = Upload::store($_FILES['logo'] ?? ['error' => UPLOAD_ERR_NO_FILE], 'branding');
        if ($err || !$info || !in_array($info['mime'], ['image/png', 'image/jpeg'], true)) {
            if ($info && ($p = Upload::path($info['stored_name']))) {
                @unlink($p); // a PDF was stored by the general upload rules: do not keep it
            }
            flash('error', $err ?: 'Choose a PNG or JPG image for the logo.');
            redirect('setup');
        }
        setting_set('school_logo', $info['stored_name']);
        audit('logo_uploaded', 'settings');
        flash('success', 'Logo uploaded. It now appears on the top menu bar, sign-in page and report cards.');
        redirect('setup');
    }

    /** Public: the school's own background photo (sign-in page and page background). */
    public function bgImage(): void
    {
        $stored = setting('bg_photo');
        $path = $stored ? Upload::path($stored) : null;
        if (!$path) {
            header('Location: ' . asset('img/children.svg'));
            exit;
        }
        header('Content-Type: ' . (str_ends_with($stored, '.png') ? 'image/png' : 'image/jpeg'));
        header('Cache-Control: public, max-age=86400');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function saveBackground(): void
    {
        $mode = in_array($_POST['bg_mode'] ?? '', ['school', 'illustration', 'photo', 'off'], true) ? $_POST['bg_mode'] : 'school';
        if (!empty($_FILES['bg_photo']) && ($_FILES['bg_photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            [$info, $err] = Upload::store($_FILES['bg_photo'], 'branding');
            if ($err || !$info || !in_array($info['mime'], ['image/png', 'image/jpeg'], true)) {
                flash('error', $err ?: 'Choose a PNG or JPG photo.');
                redirect('setup');
            }
            setting_set('bg_photo', $info['stored_name']);
            $mode = 'photo';
        }
        if ($mode === 'photo' && setting('bg_photo') === '') {
            flash('error', 'Upload a photo first, or choose the illustration.');
            redirect('setup');
        }
        setting_set('bg_mode', $mode);
        audit('background_changed', 'settings', null, ['mode' => $mode]);
        flash('success', $mode === 'off' ? 'Background picture switched off.' : 'Background picture saved. It shows on the sign-in page and behind every page.');
        redirect('setup');
    }

    /** Apply database updates shipped with a new version of the code. */
    public function migrate(): void
    {
        $applied = Migrator::run(db()->pdo());
        audit('database_migrated', 'migrations', null, $applied);
        flash('success', $applied ? 'Database updated: ' . implode(', ', $applied) : 'Database is already up to date.');
        back('dashboard');
    }

    // ---- Academic years & terms ------------------------------------------------

    public function years(): void
    {
        $years = db()->all('SELECT * FROM academic_years ORDER BY start_date DESC');
        $terms = [];
        foreach (db()->all('SELECT * FROM terms ORDER BY term_no') as $t) {
            $terms[$t['academic_year_id']][] = $t;
        }
        $this->view('setup/years', ['title' => 'Academic years & terms', 'years' => $years, 'terms' => $terms]);
    }

    public function saveYear(): void
    {
        $in = $this->only(['name', 'start_date', 'end_date']);
        $this->validate($in, [
            'name'       => 'required|max:20|unique:academic_years,name',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ], 'setup/years');

        db()->transaction(function (Database $d) use ($in) {
            $yid = $d->insert('academic_years', $in + ['is_current' => (int) $d->value('SELECT COUNT(*) FROM academic_years') === 0 ? 1 : 0]);
            $start = new DateTime($in['start_date']);
            $days = (int) $start->diff(new DateTime($in['end_date']))->days;
            for ($i = 1; $i <= 3; $i++) {
                $ts = (clone $start)->modify('+' . (int) floor(($i - 1) * $days / 3) . ' days');
                $te = $i === 3 ? new DateTime($in['end_date']) : (clone $start)->modify('+' . ((int) floor($i * $days / 3) - 1) . ' days');
                $d->insert('terms', ['academic_year_id' => $yid, 'term_no' => $i, 'name' => "Term $i", 'start_date' => $ts->format('Y-m-d'), 'end_date' => $te->format('Y-m-d')]);
            }
            audit('year_created', 'academic_years', $yid, $in);
        });
        flash('success', "Academic year {$in['name']} created with three terms. Adjust the term dates below.");
        redirect('setup/years');
    }

    public function setCurrentYear(int $id): void
    {
        $year = db()->one('SELECT * FROM academic_years WHERE id = ?', [$id]) ?? $this->notFound();
        db()->transaction(function (Database $d) use ($id) {
            $d->run('UPDATE academic_years SET is_current = (id = ?)', [$id]);
            $d->run('UPDATE terms SET is_current = 0');
            $first = $d->value('SELECT id FROM terms WHERE academic_year_id = ? ORDER BY term_no LIMIT 1', [$id]);
            if ($first) {
                $d->run('UPDATE terms SET is_current = 1 WHERE id = ?', [$first]);
            }
        });
        audit('year_set_current', 'academic_years', $id);
        flash('success', "{$year['name']} is now the current academic year.");
        redirect('setup/years');
    }

    public function saveTerm(int $id): void
    {
        $term = db()->one('SELECT t.*, y.start_date AS ys, y.end_date AS ye FROM terms t JOIN academic_years y ON y.id = t.academic_year_id WHERE t.id = ?', [$id]) ?? $this->notFound();
        $in = $this->only(['name', 'start_date', 'end_date']);
        $errors = Validator::check($in, [
            'name'       => 'required|max:30',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);
        if (!$errors && ($in['start_date'] < $term['ys'] || $in['end_date'] > $term['ye'])) {
            $errors['start_date'] = 'Term dates must fall inside the academic year.';
        }
        if ($errors) {
            flash('error', reset($errors));
            redirect('setup/years');
        }
        db()->update('terms', $in, 'id = ?', [$id]);
        audit('term_updated', 'terms', $id, $in);
        flash('success', "{$in['name']} saved.");
        redirect('setup/years');
    }

    public function setCurrentTerm(int $id): void
    {
        $term = db()->one('SELECT * FROM terms WHERE id = ?', [$id]) ?? $this->notFound();
        db()->transaction(function (Database $d) use ($term) {
            $d->run('UPDATE academic_years SET is_current = (id = ?)', [$term['academic_year_id']]);
            $d->run('UPDATE terms SET is_current = (id = ?)', [$term['id']]);
        });
        audit('term_set_current', 'terms', $id);
        flash('success', "{$term['name']} is now the current term.");
        redirect('setup/years');
    }

    // ---- Classes ----------------------------------------------------------------

    public function classes(): void
    {
        $yid = current_year()['id'] ?? 0;
        $classes = db()->all(
            "SELECT c.*, l.name AS level_name, l.sort_order, u.name AS teacher_name,
                    (SELECT COUNT(*) FROM enrolments e WHERE e.class_id = c.id AND e.academic_year_id = ? AND e.status = 'active') AS pupils
             FROM classes c JOIN levels l ON l.id = c.level_id LEFT JOIN users u ON u.id = c.class_teacher_id
             ORDER BY l.sort_order, c.name",
            [$yid]
        );
        $levels = db()->all('SELECT * FROM levels ORDER BY sort_order');
        $teachers = db()->all("SELECT id, name FROM users WHERE is_active = 1 AND role IN (" . Auth::sqlRoles(['class_teacher', 'subject_teacher', 'head_teacher', 'academic_coordinator']) . ") ORDER BY name");
        $this->view('setup/classes', ['title' => 'Classes', 'classes' => $classes, 'levels' => $levels, 'teachers' => $teachers]);
    }

    public function saveClass(int $id = 0): void
    {
        $in = $this->only(['level_id', 'name', 'section', 'class_teacher_id', 'capacity']);
        $in['is_active'] = isset($_POST['is_active']) || $id === 0 ? 1 : 0;
        $errors = Validator::check($in, [
            'level_id'         => 'required|int',
            'name'             => 'required|max:40|unique:classes,name,' . $id,
            'section'          => 'max:10',
            'class_teacher_id' => 'int',
            'capacity'         => 'required|int',
        ]);
        if (!$errors && !db()->value('SELECT id FROM levels WHERE id = ?', [$in['level_id']])) {
            $errors['level_id'] = 'Unknown level.';
        }
        if ($errors) {
            flash('error', reset($errors));
            redirect('setup/classes');
        }
        if ($id) {
            db()->update('classes', $in, 'id = ?', [$id]);
            audit('class_updated', 'classes', $id, $in);
        } else {
            $id = db()->insert('classes', $in);
            // Give the new class the default subjects of its department
            db()->run(
                'INSERT IGNORE INTO class_subjects (class_id, subject_id)
                 SELECT ?, s.id FROM subjects s JOIN levels l ON l.id = ? WHERE s.is_active = 1 AND FIND_IN_SET(l.category, s.applies_to) > 0',
                [$id, $in['level_id']]
            );
            audit('class_created', 'classes', $id, $in);
        }
        Timetable::syncTeachers(); // class teacher may have changed
        flash('success', "Class {$in['name']} saved.");
        redirect('setup/classes');
    }

    // ---- Subjects ---------------------------------------------------------------

    public function subjects(): void
    {
        $this->view('setup/subjects', ['title' => 'Subjects', 'subjects' => db()->all('SELECT * FROM subjects ORDER BY is_active DESC, name')]);
    }

    public function saveSubject(int $id = 0): void
    {
        $in = $this->only(['name', 'code']);
        $cats = array_values(array_intersect((array) ($_POST['applies_to'] ?? []), ['creche', 'kg', 'primary', 'jhs']));
        $in['applies_to'] = implode(',', $cats);
        $in['is_active'] = isset($_POST['is_active']) || $id === 0 ? 1 : 0;
        $in['is_core'] = isset($_POST['is_core']) ? 1 : 0;
        $in['code'] = strtoupper((string) $in['code']);
        $errors = Validator::check($in, [
            'name' => 'required|max:80|unique:subjects,name,' . $id,
            'code' => 'required|max:12|unique:subjects,code,' . $id,
        ]);
        if (!$cats) {
            $errors['applies_to'] = 'Choose at least one level group.';
        }
        if ($errors) {
            flash('error', reset($errors));
            redirect('setup/subjects');
        }
        if ($id) {
            db()->update('subjects', $in, 'id = ?', [$id]);
        } else {
            $id = db()->insert('subjects', $in);
        }
        audit('subject_saved', 'subjects', $id, $in);
        flash('success', "Subject {$in['name']} saved.");
        redirect('setup/subjects');
    }
}
