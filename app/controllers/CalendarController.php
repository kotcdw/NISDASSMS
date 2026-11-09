<?php
declare(strict_types=1);

class CalendarController extends Controller
{
    /** Categories where a Saturday date needs explicit confirmation (school activity on Sabbath). */
    private const SABBATH_CHECK = ['event', 'sports', 'exam', 'pta', 'staff', 'deadline'];

    public function index(): void
    {
        $month = (string) ($_GET['month'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month) || !checkdate((int) substr($month, 5, 2), 1, (int) substr($month, 0, 4))) {
            $month = date('Y-m');
        }
        $first = new DateTime("$month-01");
        $gridStart = (clone $first)->modify('-' . ((int) $first->format('N') - 1) . ' days'); // Monday
        $last = (clone $first)->modify('last day of this month');
        $gridEnd = (clone $last)->modify('+' . (7 - (int) $last->format('N')) . ' days');

        [$where, $p] = $this->filters();
        $events = db()->all(
            "SELECT * FROM calendar_events WHERE start_date <= ? AND end_date >= ? AND $where ORDER BY start_date, start_time",
            array_merge([$gridEnd->format('Y-m-d'), $gridStart->format('Y-m-d')], $p)
        );

        $byDay = [];
        foreach ($events as $ev) {
            $d = max(new DateTime($ev['start_date']), clone $gridStart);
            $end = min(new DateTime($ev['end_date']), clone $gridEnd);
            while ($d <= $end) {
                $byDay[$d->format('Y-m-d')][] = $ev;
                $d->modify('+1 day');
            }
        }

        $this->view('calendar/index', [
            'title'     => 'Academic Calendar',
            'first'     => $first,
            'gridStart' => $gridStart,
            'gridEnd'   => $gridEnd,
            'byDay'     => $byDay,
            'prev'      => (clone $first)->modify('-1 month')->format('Y-m'),
            'next'      => (clone $first)->modify('+1 month')->format('Y-m'),
            'category'  => (string) ($_GET['category'] ?? ''),
        ]);
    }

    public function listView(): void
    {
        $terms = db()->all('SELECT t.*, y.name AS year_name FROM terms t JOIN academic_years y ON y.id = t.academic_year_id ORDER BY t.start_date DESC');
        $termId = (int) ($_GET['term'] ?? (current_term()['id'] ?? 0));
        $term = null;
        foreach ($terms as $t) {
            if ((int) $t['id'] === $termId) {
                $term = $t;
            }
        }
        [$where, $p] = $this->filters('e.category', 'e.'); // levels also has a category column
        if ($term) {
            $events = db()->all(
                "SELECT e.*, l.name AS level_name, c.name AS class_name FROM calendar_events e LEFT JOIN levels l ON l.id = e.level_id LEFT JOIN classes c ON c.id = e.class_id
                 WHERE e.start_date <= ? AND e.end_date >= ? AND $where ORDER BY e.start_date, e.start_time",
                array_merge([$term['end_date'], $term['start_date']], $p)
            );
        } else {
            $events = db()->all(
                "SELECT e.*, l.name AS level_name, c.name AS class_name FROM calendar_events e LEFT JOIN levels l ON l.id = e.level_id LEFT JOIN classes c ON c.id = e.class_id
                 WHERE e.end_date >= CURDATE() AND $where ORDER BY e.start_date, e.start_time LIMIT 200",
                $p
            );
        }
        $this->view('calendar/list', [
            'title' => 'Calendar — list', 'events' => $events, 'terms' => $terms, 'term' => $term,
            'category' => (string) ($_GET['category'] ?? ''),
        ]);
    }

    public function show(int $id): void
    {
        $ev = db()->one(
            'SELECT e.*, l.name AS level_name, c.name AS class_name, u.name AS author, t.name AS term_name
             FROM calendar_events e LEFT JOIN levels l ON l.id = e.level_id LEFT JOIN classes c ON c.id = e.class_id LEFT JOIN users u ON u.id = e.created_by
             LEFT JOIN terms t ON t.id = e.term_id WHERE e.id = ? AND ' . CalendarEvent::visibilitySql('e.'),
            [$id]
        ) ?? $this->notFound();
        $this->view('calendar/show', [
            'title' => $ev['title'], 'ev' => $ev,
            'canManageEvent' => can('calendar.manage')
                && (Auth::classScope() === null || ($ev['audience'] === 'class' && Auth::classInScope((int) $ev['class_id']))),
        ]);
    }

    public function create(): void
    {
        $date = (string) ($_GET['date'] ?? '');
        $this->form(null, Validator::isDate($date) ? $date : date('Y-m-d'));
    }

    public function edit(int $id): void
    {
        $ev = db()->one('SELECT * FROM calendar_events WHERE id = ?', [$id]) ?? $this->notFound();
        $this->assertManageScope($ev);
        $this->form($ev, $ev['start_date']);
    }

    public function store(): void
    {
        $data = $this->validated('calendar/create');
        $data['created_by'] = Auth::id();
        $id = db()->insert('calendar_events', $data);
        audit('event_created', 'calendar_events', $id, ['title' => $data['title'], 'start' => $data['start_date']]);
        flash('success', 'Event added to the calendar.');
        redirect('calendar', ['month' => substr($data['start_date'], 0, 7)]);
    }

    public function update(int $id): void
    {
        $ev = db()->one('SELECT * FROM calendar_events WHERE id = ?', [$id]) ?? $this->notFound();
        $this->assertManageScope($ev);
        $old = db()->one('SELECT start_date, reminder_days FROM calendar_events WHERE id = ?', [$id]);
        $data = $this->validated("calendar/$id/edit");
        if ($old['start_date'] !== $data['start_date'] || (isset($data['reminder_days']) && (int) $old['reminder_days'] !== $data['reminder_days'])) {
            $data['reminded_at'] = null; // date moved: remind again
        }
        db()->update('calendar_events', $data, 'id = ?', [$id]);
        audit('event_updated', 'calendar_events', $id, ['title' => $data['title'], 'start' => $data['start_date']]);
        flash('success', 'Event updated.');
        redirect('calendar/' . $id);
    }

    public function destroy(int $id): void
    {
        $ev = db()->one('SELECT * FROM calendar_events WHERE id = ?', [$id]) ?? $this->notFound();
        $this->assertManageScope($ev);
        db()->run('DELETE FROM calendar_events WHERE id = ?', [$id]);
        audit('event_deleted', 'calendar_events', $id, ['title' => $ev['title'], 'start' => $ev['start_date']]);
        flash('success', 'Event deleted.');
        redirect('calendar', ['month' => substr($ev['start_date'], 0, 7)]);
    }

    /** iCalendar export so staff/parents can subscribe in Google Calendar or their phone. */
    public function ics(): void
    {
        $events = db()->all('SELECT * FROM calendar_events WHERE end_date >= (CURDATE() - INTERVAL 90 DAY) AND ' . CalendarEvent::visibilitySql() . ' ORDER BY start_date');
        $esc = fn(string $s) => addcslashes(str_replace(["\r\n", "\n"], '\\n', $s), ',;\\');
        $host = preg_replace('/[^a-z0-9.-]/i', '', $_SERVER['HTTP_HOST'] ?? 'nisdas.local');
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//NISDAS SMS//Academic Calendar//EN', 'CALSCALE:GREGORIAN', 'X-WR-CALNAME:' . $esc(setting('school_name', 'NISDAS') . ' Calendar'), 'X-WR-TIMEZONE:Africa/Accra'];
        foreach ($events as $ev) {
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:event-' . $ev['id'] . '@' . $host;
            $lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
            if ($ev['start_time']) {
                $lines[] = 'DTSTART;TZID=Africa/Accra:' . date('Ymd', strtotime($ev['start_date'])) . 'T' . str_replace(':', '', substr($ev['start_time'], 0, 8));
                $endTime = $ev['end_time'] ?: $ev['start_time'];
                $lines[] = 'DTEND;TZID=Africa/Accra:' . date('Ymd', strtotime($ev['end_date'])) . 'T' . str_replace(':', '', substr($endTime, 0, 8));
            } else {
                $lines[] = 'DTSTART;VALUE=DATE:' . date('Ymd', strtotime($ev['start_date']));
                $lines[] = 'DTEND;VALUE=DATE:' . date('Ymd', strtotime($ev['end_date'] . ' +1 day'));
            }
            $lines[] = 'SUMMARY:' . $esc($ev['title']);
            if ($ev['description']) {
                $lines[] = 'DESCRIPTION:' . $esc($ev['description']);
            }
            $lines[] = 'CATEGORIES:' . $esc(CalendarEvent::label($ev['category']));
            $lines[] = 'END:VEVENT';
        }
        $lines[] = 'END:VCALENDAR';
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="nisdas-calendar.ics"');
        echo implode("\r\n", $lines) . "\r\n";
    }

    // ---------------------------------------------------------------------------

    private function filters(string $col = 'category', string $alias = ''): array
    {
        $where = CalendarEvent::visibilitySql($alias);
        $p = [];
        $cat = (string) ($_GET['category'] ?? '');
        if ($cat !== '' && isset(CalendarEvent::CATEGORIES[$cat])) {
            $where .= ' AND ' . $col . ' = ?';
            $p[] = $cat;
        }
        return [$where, $p];
    }

    private function form(?array $ev, string $date): void
    {
        [$scope, $params] = Auth::scopeSql('c.id');
        $this->view('calendar/form', [
            'title'  => $ev ? 'Edit event' : 'New event',
            'ev'     => $ev,
            'date'   => $date,
            'levels' => db()->all('SELECT id, name FROM levels ORDER BY sort_order'),
            'classes' => db()->all("SELECT c.id, c.name FROM classes c WHERE c.is_active = 1 AND $scope ORDER BY c.name", $params),
            'classScoped' => Auth::classScope() !== null,
        ]);
    }

    private function validated(string $backRoute): array
    {
        $in = $this->only(['title', 'description', 'category', 'start_date', 'end_date', 'start_time', 'end_time', 'audience', 'level_id', 'class_id']);
        $scoped = Auth::classScope() !== null;
        if ($scoped) {
            $in['audience'] = 'class';
            $in['level_id'] = null;
            $in['is_school_closed'] = 0;
        }
        $in['end_date'] = $in['end_date'] ?: $in['start_date'];
        $in['is_school_closed'] = $scoped ? 0 : (isset($_POST['is_school_closed']) ? 1 : 0);
        if (can('messages.send')) { // only people allowed to SMS parents can switch reminders on/off
            $in['notify_parents'] = isset($_POST['notify_parents']) && ($in['audience'] ?? '') !== 'staff' ? 1 : 0; // staff-only events never text parents
            $in['reminder_days'] = max(0, min(14, (int) ($_POST['reminder_days'] ?? 1)));
        }

        $errors = Validator::check($in, [
            'title'      => 'required|max:150',
            'category'   => 'required|in:' . implode(',', array_keys(CalendarEvent::CATEGORIES)),
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'audience'   => 'required|in:' . implode(',', array_keys(CalendarEvent::AUDIENCES)),
            'level_id'   => 'int',
            'class_id'   => 'int',
        ]);
        foreach (['start_time', 'end_time'] as $t) {
            if ($in[$t] !== null && !preg_match('/^([01]\d|2[0-3]):[0-5]\d(:00)?$/', (string) $in[$t])) {
                $errors[$t] = 'Use a valid time (HH:MM).';
            }
        }
        if ($in['audience'] === 'level' && !$in['level_id']) {
            $errors['level_id'] = 'Choose the level this event is for.';
        }
        if ($in['audience'] === 'class' && (!$in['class_id'] || !Auth::classInScope((int) $in['class_id']) || !db()->value('SELECT id FROM classes WHERE id = ? AND is_active = 1', [(int) $in['class_id']]))) {
            $errors['class_id'] = 'Choose a class you are allowed to manage.';
        }
        if ($in['audience'] !== 'level') {
            $in['level_id'] = null;
        }
        if ($in['audience'] !== 'class') {
            $in['class_id'] = null;
        }

        if (!$errors && setting('sabbath_guard', '1') === '1'
            && in_array($in['category'], self::SABBATH_CHECK, true) && !$in['is_school_closed']
            && CalendarEvent::touchesSabbath($in['start_date'], $in['end_date'])
            && empty($_POST['sabbath_ok'])) {
            $errors['start_date'] = 'This date falls on the Sabbath (Saturday). Choose another day, or tick the confirmation box if it is approved.';
            $in['_sabbath'] = 1;
        }

        if ($errors) {
            with_old($in, $errors);
            flash('error', 'Please correct the highlighted fields.');
            redirect($backRoute);
        }

        // Link to the term that contains the start date, if any
        $in['term_id'] = db()->value('SELECT id FROM terms WHERE ? BETWEEN start_date AND end_date LIMIT 1', [$in['start_date']]);
        return $in;
    }

    private function assertManageScope(array $event): void
    {
        if (Auth::classScope() !== null && ($event['audience'] !== 'class' || !Auth::classInScope((int) ($event['class_id'] ?? 0)))) {
            $this->notFound();
        }
    }
}
