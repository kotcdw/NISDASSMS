<?php
declare(strict_types=1);

/**
 * NISDAS APPS: the launcher page — every part of the system as a large colourful icon in one grid.
 * Icons are original artwork drawn for NISDAS (simple gradient shapes), not copies of anyone's logos.
 */
final class Engine
{
    /**
     * The NISDAS APPS launcher starts with these 21 tiles (from the school's picture), in this order, the same for everyone.
     * key => [label, [[route, permission, query params], …]] — the first target the person may open is used;
     * when they may open none, the tile still shows and its page answers "Access denied".
     */
    public const TILES = [
        'sis'        => ['SIS', [['pupils', 'pupils.view'], ['portal', 'portal.parent'], ['learn', 'quiz.take']]],
        'course'     => ['Subject Manager', [['curriculum', 'scheme.plan'], ['curriculum', 'curriculum.manage']]],
        'financial'  => ['Financial', [['finance', 'finance.view'], ['fees', 'fees.view'], ['requisitions', 'requisitions.create']]],
        'admission'  => ['Online Admission', [['admission', 'admission.view'], ['apply', 'auth']]],
        'gradebook'  => ['Gradebook', [['exams', 'exams.enter']]],
        'exam'       => ['Exam', [['exam-timetable', 'timetable.view'], ['bece', 'bece.view']]],
        'lms'        => ['LMS', [['lms', 'lms.manage'], ['lms', 'lms.view'], ['lms', 'portal.parent']]],
        'attendance' => ['Attendance', [['attendance', 'attendance.view'], ['attendance/staff', 'staff_att.view']]],
        'assignment' => ['Assignment', [['assignments', 'assignments.manage'], ['homework', 'assignments.submit']]],
        'live'       => ['Live Classroom', [['live', 'live.manage'], ['live', 'live.join'], ['live', 'portal.parent']]],
        'timetable'  => ['Timetable', [['timetable', 'timetable.view'], ['calendar', 'calendar.view']]],
        'library'    => ['Library', [['library', 'library.view']]],
        'crm'        => ['CRM', [['crm', 'crm.manage']]],
        'event'      => ['Event', [['events', 'events.manage'], ['events', 'staff'], ['events', 'portal.parent'], ['events', 'auth']]],
        'document'   => ['Document', [['documents', 'documents.view']]],
        'whatsapp'   => ['WhatsApp', [['messages/outbox', 'messages.send'], ['conversations', 'chat.use']]],
        'zoom'       => ['Zoom', [['live', 'live.manage', ['provider' => 'zoom']], ['live', 'live.join', ['provider' => 'zoom']], ['live', 'portal.parent', ['provider' => 'zoom']]]],
        'meet'       => ['Google Meet', [['live', 'live.manage', ['provider' => 'meet']], ['live', 'live.join', ['provider' => 'meet']], ['live', 'portal.parent', ['provider' => 'meet']]]],
        'teams'      => ['Teams', [['live', 'live.manage', ['provider' => 'teams']], ['live', 'live.join', ['provider' => 'teams']], ['live', 'portal.parent', ['provider' => 'teams']]]],
    ];

    /**
     * All launcher tiles: key => [label, href, icon]. First the 21 picture tiles (everyone; when the person may open
     * none of a tile's pages it still shows and the page answers "Access denied"), then every other app this person
     * may open, in menu order, without repeating a page a picture tile already opens.
     * Administration apps are not tiles: they live in the Administration menu on the top menu bar.
     */
    public static function tiles(): array
    {
        $out = [];
        $used = [];
        foreach (self::TILES as $k => [$label, $targets]) {
            $pick = null;
            foreach ($targets as $t) {
                if (Modules::target(['', '', '', '', '', [[$t[0], $t[1]]]]) !== null) {
                    $pick = $t;
                    break;
                }
            }
            if ($pick === null) {
                continue; // least privilege: no tile for an app this role cannot open
            }
            $p = $pick;
            $out[$k] = [$label, url($p[0], $p[2] ?? []), $k];
            $used[$p[0]] = true;
        }
        $role = Auth::role();
        foreach (Menu::visible() as [$r, $label, $grp, $icon]) {
            if ($grp === Menu::ADMIN || isset($used[$r]) || ($role === 'super_admin' && in_array($r, ['portal', 'learn'], true))) {
                continue; // an Administration app, already a tile, or a Super Admin preview of the parent/pupil pages (on School setup)
            }
            $out['app:' . $r] = [$label, url($r), $icon];
            $used[$r] = true;
        }
        return $out;
    }

    /** The Administration menu on the top menu bar: the admin apps this person may open, [[label, href, route], …]. */
    public static function admin(): array
    {
        $out = [];
        foreach (Menu::visible() as [$r, $label, $grp]) {
            if ($grp === Menu::ADMIN) {
                $out[] = [$label, url($r), $r];
            }
        }
        return $out;
    }

    /** Large icon for a tile (64×64 artwork). */
    public static function icon(string $key, int $size = 72): string
    {
        // Each drawing gets its own gradient ids, so the same icon can appear twice on one page
        static $n = 0;
        $p = 'eg' . preg_replace('/[^a-z]/', '', $key) . (++$n) . '-';
        $lin = fn(string $id, string $a, string $b, string $dir = 'v') => '<linearGradient id="' . $p . $id . '" x1="0" y1="0" x2="' . ($dir === 'h' ? '1' : '0.35') . '" y2="' . ($dir === 'h' ? '0' : '1') . '"><stop offset="0" stop-color="' . $a . '"/><stop offset="1" stop-color="' . $b . '"/></linearGradient>';
        $f = fn(string $id) => 'url(#' . $p . $id . ')';
        switch ($key) {
            case 'sis': // ID card with photo and a mortarboard
                $d = $lin('a', '#4f9cf9', '#1d4ed8') . $lin('b', '#0f2b5b', '#1e3a8a');
                $g = '<rect x="6" y="16" width="52" height="36" rx="6" fill="' . $f('a') . '"/><rect x="6" y="16" width="52" height="8" rx="4" fill="#fff" opacity=".18"/>'
                   . '<circle cx="22" cy="35" r="7" fill="#fff"/><path d="M12 48c0-6 4.5-8.5 10-8.5S32 42 32 48z" fill="#fff" opacity=".9"/>'
                   . '<rect x="36" y="31" width="16" height="3.5" rx="1.7" fill="#fff" opacity=".9"/><rect x="36" y="38" width="12" height="3.5" rx="1.7" fill="#fff" opacity=".6"/>'
                   . '<path d="M22 6l13 5-13 5-13-5z" fill="' . $f('b') . '"/><path d="M15 13v4c0 2 3.5 3.5 7 3.5s7-1.5 7-3.5v-4l-7 2.7z" fill="' . $f('b') . '"/><rect x="33.5" y="11" width="1.6" height="7" fill="#f6b73c"/>';
                break;
            case 'course': // open book with a ribbon bookmark
                $d = $lin('a', '#5cc8f7', '#0e8ad8') . $lin('b', '#9fdcfb', '#3db1ee');
                $g = '<path d="M32 16c-7-5-16-6-25-4v38c9-2 18-1 25 4z" fill="' . $f('a') . '"/><path d="M32 16c7-5 16-6 25-4v38c-9-2-18-1-25 4z" fill="' . $f('b') . '"/>'
                   . '<rect x="31" y="15" width="2" height="39" fill="#0b5fa3" opacity=".5"/>'
                   . '<path d="M12 22c5-1 10-.5 15 1.5M12 29c5-1 10-.5 15 1.5M12 36c5-1 10-.5 15 1.5" stroke="#fff" stroke-width="2.4" stroke-linecap="round" fill="none" opacity=".85"/>'
                   . '<path d="M44 10h7v18l-3.5-3-3.5 3z" fill="#ef4f62"/>';
                break;
            case 'financial': // banknotes with a coin stack
                $d = $lin('a', '#34c58b', '#0f8a5f') . $lin('b', '#ffd36b', '#f29b1d') . $lin('c', '#5ad8a4', '#18a071');
                $g = '<rect x="6" y="14" width="44" height="26" rx="4" fill="' . $f('c') . '" transform="rotate(-6 28 27)"/><rect x="8" y="20" width="44" height="26" rx="4" fill="' . $f('a') . '"/>'
                   . '<circle cx="30" cy="33" r="7" fill="#fff" opacity=".9"/><text x="30" y="37" text-anchor="middle" font-family="Arial,sans-serif" font-size="10" font-weight="700" fill="#0f8a5f">₵</text>'
                   . '<rect x="12" y="24" width="6" height="2.5" rx="1" fill="#fff" opacity=".6"/>'
                   . '<ellipse cx="48" cy="54" rx="10" ry="3.6" fill="#d17c0a"/><rect x="38" y="44" width="20" height="10" fill="' . $f('b') . '"/><ellipse cx="48" cy="44" rx="10" ry="3.6" fill="#ffe29a"/><path d="M38 49c2 2.2 18 2.2 20 0" stroke="#d17c0a" stroke-width="1.2" fill="none"/>';
                break;
            case 'admission': // web form in a browser window with a tick
                $d = $lin('a', '#5aa9ff', '#2563eb') . $lin('b', '#3ddc84', '#11a85a');
                $g = '<rect x="6" y="10" width="46" height="40" rx="5" fill="' . $f('a') . '"/><rect x="6" y="10" width="46" height="9" rx="4.5" fill="#123e94" opacity=".55"/>'
                   . '<circle cx="12" cy="14.5" r="1.6" fill="#ff8a7a"/><circle cx="17" cy="14.5" r="1.6" fill="#ffd36b"/><circle cx="22" cy="14.5" r="1.6" fill="#7ee2a8"/>'
                   . '<rect x="12" y="25" width="30" height="5" rx="2" fill="#fff" opacity=".95"/><rect x="12" y="34" width="30" height="5" rx="2" fill="#fff" opacity=".75"/><rect x="12" y="43" width="16" height="3" rx="1.5" fill="#fff" opacity=".6"/>'
                   . '<circle cx="48" cy="48" r="11" fill="' . $f('b') . '" stroke="#fff" stroke-width="2.5"/><path d="M42.5 48.5l4 4 7-8" stroke="#fff" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round"/>';
                break;
            case 'gradebook': // report sheet with bars and a medal
                $d = $lin('a', '#eaf3ff', '#cfe3ff') . $lin('b', '#3b82f6', '#1e5bd8') . $lin('c', '#ffd36b', '#f29b1d');
                $g = '<rect x="10" y="6" width="38" height="50" rx="5" fill="' . $f('a') . '" stroke="#9cc3f5" stroke-width="1.5"/><rect x="10" y="6" width="38" height="10" rx="5" fill="' . $f('b') . '"/>'
                   . '<rect x="16" y="38" width="5" height="12" rx="1.5" fill="#60a5fa"/><rect x="24" y="30" width="5" height="20" rx="1.5" fill="#3b82f6"/><rect x="32" y="24" width="5" height="26" rx="1.5" fill="#1d4ed8"/>'
                   . '<rect x="16" y="21" width="14" height="3" rx="1.5" fill="#1e5bd8" opacity=".5"/>'
                   . '<path d="M44 44l-4 14 5-3 3 5 2-14zM52 44l4 14-5-3-3 5-2-14z" fill="#ef4f62"/><circle cx="48" cy="42" r="9" fill="' . $f('c') . '" stroke="#fff" stroke-width="2"/><path d="M48 37.5l1.4 2.9 3.1.4-2.3 2.2.6 3.1-2.8-1.5-2.8 1.5.6-3.1-2.3-2.2 3.1-.4z" fill="#fff"/>';
                break;
            case 'exam': // answer sheet with ticks and a pencil
                $d = $lin('a', '#3fd6c0', '#0f9f8c') . $lin('b', '#ffd36b', '#f5a524');
                $g = '<rect x="8" y="6" width="38" height="52" rx="5" fill="' . $f('a') . '"/><rect x="14" y="13" width="26" height="3" rx="1.5" fill="#fff" opacity=".9"/>'
                   . '<path d="M14 25l3 3 5-6M14 36l3 3 5-6M14 47l3 3 5-6" stroke="#fff" stroke-width="2.6" fill="none" stroke-linecap="round" stroke-linejoin="round"/>'
                   . '<rect x="26" y="24" width="14" height="3" rx="1.5" fill="#fff" opacity=".75"/><rect x="26" y="35" width="14" height="3" rx="1.5" fill="#fff" opacity=".75"/><rect x="26" y="46" width="10" height="3" rx="1.5" fill="#fff" opacity=".75"/>'
                   . '<g transform="rotate(35 50 36)"><rect x="46" y="12" width="8" height="34" rx="1.5" fill="' . $f('b') . '"/><rect x="46" y="12" width="8" height="5" rx="1.5" fill="#ef7f9a"/><path d="M46 46h8l-4 8z" fill="#f6dcae"/><path d="M48.7 51.3h2.6l-1.3 2.7z" fill="#374151"/></g>';
                break;
            case 'lms': // laptop playing a lesson
                $d = $lin('a', '#2b4c8c', '#14284f') . $lin('b', '#55b3ff', '#2f7fe0') . $lin('c', '#c9d3e3', '#9aa8bf');
                $g = '<rect x="10" y="12" width="44" height="30" rx="4" fill="' . $f('a') . '"/><rect x="13" y="15" width="38" height="24" rx="2" fill="' . $f('b') . '"/>'
                   . '<circle cx="32" cy="27" r="7.5" fill="#fff" opacity=".95"/><path d="M30 23.5v7l6-3.5z" fill="#2f7fe0"/>'
                   . '<rect x="17" y="35" width="30" height="1.8" rx=".9" fill="#fff" opacity=".5"/><rect x="17" y="35" width="12" height="1.8" rx=".9" fill="#ffd36b"/>'
                   . '<path d="M4 44h56l-4 6H8z" fill="' . $f('c') . '"/><rect x="27" y="44" width="10" height="2" rx="1" fill="#7c8aa3"/>';
                break;
            case 'attendance': // calendar register with a tick badge
                $d = $lin('a', '#ffb547', '#f5871f') . $lin('b', '#3b8cff', '#1d5fd6');
                $g = '<rect x="6" y="10" width="44" height="42" rx="5" fill="#fff" stroke="#f3c48b" stroke-width="1.5"/><path d="M6 15a5 5 0 015-5h34a5 5 0 015 5v6H6z" fill="' . $f('a') . '"/>'
                   . '<rect x="14" y="6" width="3.5" height="9" rx="1.7" fill="#a85a12"/><rect x="38.5" y="6" width="3.5" height="9" rx="1.7" fill="#a85a12"/>'
                   . '<g fill="#fbd29b"><circle cx="15" cy="29" r="2.6"/><circle cx="24" cy="29" r="2.6"/><circle cx="33" cy="29" r="2.6"/><circle cx="42" cy="29" r="2.6"/><circle cx="15" cy="38" r="2.6"/><circle cx="24" cy="38" r="2.6"/><circle cx="15" cy="46" r="2.6"/></g>'
                   . '<circle cx="46" cy="46" r="12" fill="' . $f('b') . '" stroke="#fff" stroke-width="2.5"/><path d="M40.5 46.5l4 4 7-8" stroke="#fff" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round"/>';
                break;
            case 'assignment': // clipboard with a checklist
                $d = $lin('a', '#ff8fa3', '#e8456b') . $lin('b', '#5b6b8c', '#334155');
                $g = '<rect x="10" y="10" width="40" height="48" rx="5" fill="' . $f('a') . '"/><rect x="15" y="16" width="30" height="37" rx="2.5" fill="#fff"/>'
                   . '<rect x="21" y="5" width="18" height="9" rx="3" fill="' . $f('b') . '"/><circle cx="30" cy="9.5" r="1.8" fill="#fff"/>'
                   . '<rect x="19" y="22" width="6" height="6" rx="1.5" fill="none" stroke="#e8456b" stroke-width="1.8"/><path d="M20.3 25l1.6 1.6 3.2-3.6" stroke="#e8456b" stroke-width="1.8" fill="none" stroke-linecap="round"/>'
                   . '<rect x="19" y="33" width="6" height="6" rx="1.5" fill="none" stroke="#e8456b" stroke-width="1.8"/><path d="M20.3 36l1.6 1.6 3.2-3.6" stroke="#e8456b" stroke-width="1.8" fill="none" stroke-linecap="round"/>'
                   . '<rect x="19" y="44" width="6" height="6" rx="1.5" fill="none" stroke="#f3a5b6" stroke-width="1.8"/>'
                   . '<rect x="28" y="24" width="13" height="2.6" rx="1.3" fill="#94a3b8"/><rect x="28" y="35" width="13" height="2.6" rx="1.3" fill="#94a3b8"/><rect x="28" y="46" width="9" height="2.6" rx="1.3" fill="#cbd5e1"/>';
                break;
            case 'live': // screen with a presenter, LIVE dot and signal
                $d = $lin('a', '#1f3b70', '#0f1f3d') . $lin('b', '#38d1c4', '#159c94');
                $g = '<rect x="6" y="10" width="46" height="34" rx="5" fill="' . $f('b') . '"/><rect x="9" y="13" width="40" height="28" rx="3" fill="' . $f('a') . '"/>'
                   . '<circle cx="29" cy="24" r="5" fill="#fff"/><path d="M19 39c0-6 4.5-8.5 10-8.5S39 33 39 39z" fill="#fff" opacity=".9"/>'
                   . '<rect x="12" y="16" width="14" height="5.5" rx="2.7" fill="#ef4444"/><circle cx="15" cy="18.7" r="1.3" fill="#fff"/><rect x="17.5" y="17.8" width="6.5" height="1.8" rx=".9" fill="#fff"/>'
                   . '<rect x="22" y="44" width="14" height="5" fill="#0f1f3d"/><rect x="16" y="49" width="26" height="4" rx="2" fill="#159c94"/>'
                   . '<path d="M53 18a8 8 0 010 12M57 14a14 14 0 010 20" stroke="#38d1c4" stroke-width="2.6" fill="none" stroke-linecap="round"/>';
                break;
            case 'timetable': // weekly grid with a clock
                $d = $lin('a', '#ffcf5c', '#f59f1b') . $lin('b', '#ff7a6b', '#e8453b');
                $g = '<rect x="6" y="8" width="46" height="44" rx="5" fill="#fff7e6" stroke="#f5c46a" stroke-width="1.5"/><path d="M6 13a5 5 0 015-5h36a5 5 0 015 5v5H6z" fill="' . $f('b') . '"/>'
                   . '<g><rect x="11" y="23" width="10" height="7" rx="1.5" fill="#ffd47a"/><rect x="24" y="23" width="10" height="7" rx="1.5" fill="#7cc6ff"/><rect x="37" y="23" width="10" height="7" rx="1.5" fill="#9be3c2"/>'
                   . '<rect x="11" y="33" width="10" height="7" rx="1.5" fill="#f9a8c0"/><rect x="24" y="33" width="10" height="7" rx="1.5" fill="#ffd47a"/>'
                   . '<rect x="11" y="43" width="10" height="5" rx="1.5" fill="#7cc6ff"/></g>'
                   . '<circle cx="46" cy="46" r="12" fill="' . $f('a') . '" stroke="#fff" stroke-width="2.5"/><path d="M46 39.5V46l4.5 3" stroke="#fff" stroke-width="2.8" fill="none" stroke-linecap="round"/>';
                break;
            case 'library': // open book on a stack of books
                $d = $lin('a', '#43c7b8', '#178f82') . $lin('b', '#ff9e5a', '#e2672b') . $lin('c', '#6aa8ff', '#2f6fdc');
                $g = '<rect x="10" y="44" width="44" height="9" rx="2.5" fill="' . $f('c') . '"/><rect x="10" y="44" width="5" height="9" rx="1.5" fill="#1d4ea8"/>'
                   . '<rect x="14" y="35" width="38" height="9" rx="2.5" fill="' . $f('b') . '"/><rect x="45" y="35" width="4" height="9" fill="#b84a17"/>'
                   . '<path d="M32 18c-6-4-13-5-20-3v18c7-2 14-1 20 3z" fill="' . $f('a') . '"/><path d="M32 18c6-4 13-5 20-3v18c-7-2-14-1-20 3z" fill="#7fe0d4"/>'
                   . '<path d="M17 21c4-.8 8-.4 11 1M17 26c4-.8 8-.4 11 1" stroke="#fff" stroke-width="1.8" stroke-linecap="round" fill="none" opacity=".85"/><path d="M36 22c4-1.4 8-1.6 11-1M36 27c4-1.4 8-1.6 11-1" stroke="#178f82" stroke-width="1.8" stroke-linecap="round" fill="none" opacity=".6"/>';
                break;
            case 'crm': // people flowing into a funnel
                $d = $lin('a', '#8b7bff', '#5b3fd6') . $lin('b', '#ffb547', '#f08a1d');
                $g = '<circle cx="16" cy="10" r="4.5" fill="#ff8fa3"/><circle cx="32" cy="8" r="4.5" fill="' . $f('b') . '"/><circle cx="48" cy="10" r="4.5" fill="#46c6b4"/>'
                   . '<path d="M6 20h52L38 40v14l-12 5V40z" fill="' . $f('a') . '"/><path d="M6 20h52l-4 5H10z" fill="#fff" opacity=".25"/>'
                   . '<circle cx="32" cy="31" r="4" fill="#fff"/><path d="M30.2 31l1.3 1.3 2.5-2.7" stroke="#5b3fd6" stroke-width="1.6" fill="none" stroke-linecap="round"/>';
                break;
            case 'shop': // shopping cart with goods
                $d = $lin('a', '#ff9a5a', '#e8513b') . $lin('b', '#5aa9ff', '#2563eb') . $lin('c', '#3ddc84', '#16a34a');
                $g = '<rect x="22" y="12" width="13" height="14" rx="2" fill="' . $f('b') . '"/><rect x="33" y="16" width="14" height="10" rx="2" fill="' . $f('c') . '"/><rect x="27.5" y="12" width="2" height="14" fill="#fff" opacity=".5"/>'
                   . '<path d="M4 8h8l7 30h32l6-20H16" stroke="' . $f('a') . '" stroke-width="5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>'
                   . '<path d="M14.5 24h44L53 38H18z" fill="' . $f('a') . '" opacity=".85"/><circle cx="22" cy="50" r="5" fill="#334155"/><circle cx="48" cy="50" r="5" fill="#334155"/><circle cx="22" cy="50" r="1.8" fill="#fff"/><circle cx="48" cy="50" r="1.8" fill="#fff"/>';
                break;
            case 'event': // party popper with confetti
                $d = $lin('a', '#ff7eb6', '#b13cd6') . $lin('b', '#ffd36b', '#f59f1b');
                $g = '<path d="M8 58l12-36 22 22z" fill="' . $f('a') . '"/><path d="M14 40l6 6M11 49l4 4M17 31l14 14" stroke="#fff" stroke-width="2.4" stroke-linecap="round" opacity=".7"/>'
                   . '<path d="M30 20c2-6 8-8 12-6M40 30c6-1 10 2 12 7" stroke="#5aa9ff" stroke-width="2.6" fill="none" stroke-linecap="round"/>'
                   . '<path d="M46 6l1.8 3.7 4 .6-2.9 2.8.7 4-3.6-1.9-3.6 1.9.7-4-2.9-2.8 4-.6z" fill="' . $f('b') . '"/>'
                   . '<circle cx="30" cy="9" r="2.2" fill="#3ddc84"/><circle cx="56" cy="22" r="2.2" fill="#ff7a6b"/><rect x="50" y="44" width="4" height="4" rx="1" fill="#ffd36b" transform="rotate(20 52 46)"/><rect x="22" y="12" width="4" height="4" rx="1" fill="#ff7eb6" transform="rotate(-25 24 14)"/><circle cx="57" cy="34" r="1.8" fill="#8b7bff"/>';
                break;
            case 'document': // folder with a document
                $d = $lin('a', '#ffcf5c', '#f2a516') . $lin('b', '#ffe08a', '#f7bb2c');
                $g = '<path d="M6 16a4 4 0 014-4h14l5 5h25a4 4 0 014 4v31a4 4 0 01-4 4H10a4 4 0 01-4-4z" fill="' . $f('a') . '"/>'
                   . '<rect x="16" y="8" width="30" height="34" rx="3" fill="#fff" stroke="#e5e7eb"/><rect x="21" y="15" width="20" height="2.6" rx="1.3" fill="#60a5fa"/><rect x="21" y="21" width="20" height="2.6" rx="1.3" fill="#cbd5e1"/><rect x="21" y="27" width="14" height="2.6" rx="1.3" fill="#cbd5e1"/>'
                   . '<path d="M6 28a4 4 0 014-4h44a4 4 0 014 4v24a4 4 0 01-4 4H10a4 4 0 01-4-4z" fill="' . $f('b') . '"/><rect x="24" y="36" width="16" height="3.5" rx="1.7" fill="#fff" opacity=".8"/>';
                break;
            case 'zoom': // generic video camera with a meeting link (not the Zoom logo)
                $d = $lin('a', '#60a5fa', '#2d5be3') . $lin('b', '#93c5fd', '#3b82f6');
                $g = '<rect x="4" y="18" width="38" height="28" rx="7" fill="' . $f('a') . '"/><path d="M42 28l14-8v24l-14-8z" fill="' . $f('b') . '"/>'
                   . '<circle cx="14" cy="27" r="3" fill="#fff" opacity=".85"/><rect x="10" y="35" width="20" height="3" rx="1.5" fill="#fff" opacity=".5"/>'
                   . '<g transform="translate(36 40)" stroke="#1e3a8a" stroke-width="3" fill="none" stroke-linecap="round"><path d="M6 10l-2 2a4 4 0 01-6-6l3-3a4 4 0 016 0"/><path d="M8 4l2-2a4 4 0 016 6l-3 3a4 4 0 01-6 0"/></g>';
                break;
            case 'meet': // generic video call grid of four people (not the Google Meet logo)
                $d = $lin('a', '#2dd4bf', '#0d9488') . $lin('b', '#a3e635', '#65a30d');
                $g = '<rect x="4" y="10" width="56" height="40" rx="6" fill="#0f2f33"/>'
                   . '<rect x="8" y="14" width="23" height="15" rx="3" fill="' . $f('a') . '"/><rect x="33" y="14" width="23" height="15" rx="3" fill="' . $f('b') . '"/><rect x="8" y="31" width="23" height="15" rx="3" fill="' . $f('b') . '"/><rect x="33" y="31" width="23" height="15" rx="3" fill="' . $f('a') . '"/>'
                   . '<g fill="#fff"><circle cx="19.5" cy="20" r="2.6"/><path d="M14.5 28c0-3 2.2-4.4 5-4.4s5 1.4 5 4.4z"/><circle cx="44.5" cy="20" r="2.6"/><path d="M39.5 28c0-3 2.2-4.4 5-4.4s5 1.4 5 4.4z"/><circle cx="19.5" cy="37" r="2.6"/><path d="M14.5 45c0-3 2.2-4.4 5-4.4s5 1.4 5 4.4z"/><circle cx="44.5" cy="37" r="2.6"/><path d="M39.5 45c0-3 2.2-4.4 5-4.4s5 1.4 5 4.4z"/></g>'
                   . '<rect x="24" y="52" width="16" height="5" rx="2.5" fill="#ef4444"/>';
                break;
            case 'teams': // generic group of three people (not the Microsoft Teams logo)
                $d = $lin('a', '#8b7bff', '#5146c9') . $lin('b', '#b5aaff', '#7c6cf0');
                $g = '<circle cx="14" cy="22" r="6" fill="' . $f('b') . '"/><path d="M3 44c0-8 5-12 11-12s11 4 11 12z" fill="' . $f('b') . '"/>'
                   . '<circle cx="50" cy="22" r="6" fill="' . $f('b') . '"/><path d="M39 44c0-8 5-12 11-12s11 4 11 12z" fill="' . $f('b') . '"/>'
                   . '<circle cx="32" cy="18" r="8" fill="' . $f('a') . '" stroke="#fff" stroke-width="2"/><path d="M17 48c0-10 7-15 15-15s15 5 15 15z" fill="' . $f('a') . '" stroke="#fff" stroke-width="2"/>'
                   . '<rect x="10" y="49" width="44" height="6" rx="3" fill="#3b3291"/>';
                break;
            case 'quiz': // question bubble with a timer
                $d = $lin('a', '#8b7bff', '#5146c9') . $lin('b', '#ffcf5c', '#f59f1b');
                $g = '<path d="M8 12a6 6 0 016-6h30a6 6 0 016 6v22a6 6 0 01-6 6H24l-9 9v-9h-1a6 6 0 01-6-6z" fill="' . $f('a') . '"/>'
                   . '<text x="29" y="32" text-anchor="middle" font-family="Arial,sans-serif" font-size="22" font-weight="700" fill="#fff">?</text>'
                   . '<circle cx="47" cy="45" r="12" fill="' . $f('b') . '" stroke="#fff" stroke-width="2.5"/><path d="M47 39v6l4 2.5" stroke="#fff" stroke-width="2.6" fill="none" stroke-linecap="round"/>';
                break;
            case 'leave': // calendar page with a door / exit arrow
                $d = $lin('a', '#3fd6c0', '#0f9f8c') . $lin('b', '#ff9a5a', '#e8513b');
                $g = '<rect x="6" y="10" width="40" height="42" rx="5" fill="#fff" stroke="#9be3d6" stroke-width="1.5"/><path d="M6 15a5 5 0 015-5h30a5 5 0 015 5v6H6z" fill="' . $f('a') . '"/>'
                   . '<rect x="13" y="28" width="9" height="7" rx="1.5" fill="#bdeee6"/><rect x="25" y="28" width="9" height="7" rx="1.5" fill="#bdeee6"/><rect x="13" y="39" width="9" height="7" rx="1.5" fill="#bdeee6"/>'
                   . '<circle cx="47" cy="45" r="12" fill="' . $f('b') . '" stroke="#fff" stroke-width="2.5"/><path d="M42 45h10M48 40l5 5-5 5" stroke="#fff" stroke-width="2.6" fill="none" stroke-linecap="round" stroke-linejoin="round"/>';
                break;
            case 'appointment': // calendar with a person and a clock
                $d = $lin('a', '#5aa9ff', '#2563eb') . $lin('b', '#3ddc84', '#16a34a');
                $g = '<rect x="6" y="10" width="42" height="40" rx="5" fill="' . $f('a') . '"/><rect x="6" y="10" width="42" height="9" rx="4.5" fill="#123e94" opacity=".5"/>'
                   . '<rect x="14" y="5" width="3.5" height="9" rx="1.7" fill="#0f2b5b"/><rect x="36.5" y="5" width="3.5" height="9" rx="1.7" fill="#0f2b5b"/>'
                   . '<circle cx="24" cy="30" r="5" fill="#fff"/><path d="M15 44c0-5 4-7.5 9-7.5s9 2.5 9 7.5z" fill="#fff" opacity=".9"/>'
                   . '<circle cx="48" cy="46" r="11" fill="' . $f('b') . '" stroke="#fff" stroke-width="2.5"/><path d="M48 40.5V46l3.5 2" stroke="#fff" stroke-width="2.6" fill="none" stroke-linecap="round"/>';
                break;
            case 'reports': // pie and bars report
                $d = $lin('a', '#eaf3ff', '#cfe3ff') . $lin('b', '#5aa9ff', '#2563eb') . $lin('c', '#ffcf5c', '#f59f1b');
                $g = '<rect x="6" y="8" width="52" height="48" rx="6" fill="' . $f('a') . '" stroke="#9cc3f5" stroke-width="1.5"/>'
                   . '<circle cx="22" cy="26" r="10" fill="' . $f('b') . '"/><path d="M22 16v10h10a10 10 0 00-10-10z" fill="' . $f('c') . '"/>'
                   . '<rect x="38" y="30" width="5" height="16" rx="1.5" fill="#60a5fa"/><rect x="46" y="22" width="5" height="24" rx="1.5" fill="#2563eb"/>'
                   . '<rect x="12" y="42" width="20" height="3" rx="1.5" fill="#94a3b8"/><rect x="12" y="48" width="14" height="3" rx="1.5" fill="#cbd5e1"/>';
                break;
            case 'timesheet': // timesheet grid with a clock
                $d = $lin('a', '#ffb547', '#f5871f') . $lin('b', '#5aa9ff', '#2563eb');
                $g = '<rect x="6" y="8" width="42" height="46" rx="5" fill="#fff" stroke="#f3c48b" stroke-width="1.5"/><rect x="6" y="8" width="42" height="9" rx="4.5" fill="' . $f('a') . '"/>'
                   . '<g stroke="#f3c48b" stroke-width="1.4"><path d="M6 27h42M6 37h42M6 47h42M20 17v37"/></g><g fill="#f59f1b"><rect x="24" y="21" width="12" height="3" rx="1.5"/><rect x="24" y="31" width="16" height="3" rx="1.5"/><rect x="24" y="41" width="9" height="3" rx="1.5"/></g>'
                   . '<circle cx="47" cy="45" r="12" fill="' . $f('b') . '" stroke="#fff" stroke-width="2.5"/><path d="M47 39v6l4 2.5" stroke="#fff" stroke-width="2.6" fill="none" stroke-linecap="round"/>';
                break;
            case 'health': // first-aid kit
                $d = $lin('a', '#ff7a6b', '#e2364d');
                $g = '<rect x="22" y="8" width="20" height="10" rx="3" fill="none" stroke="#b8263a" stroke-width="3.5"/><rect x="6" y="16" width="52" height="40" rx="7" fill="' . $f('a') . '"/>'
                   . '<rect x="27" y="24" width="10" height="26" rx="2" fill="#fff"/><rect x="19" y="32" width="26" height="10" rx="2" fill="#fff"/><rect x="6" y="16" width="52" height="7" rx="3.5" fill="#fff" opacity=".18"/>';
                break;
            case 'seating': // seating plan grid of chairs
                $d = $lin('a', '#3fd6c0', '#0f9f8c') . $lin('b', '#ff8fa3', '#e8456b');
                $g = '<rect x="6" y="6" width="52" height="52" rx="7" fill="#e9fbf7" stroke="#9be3d6" stroke-width="1.5"/><rect x="14" y="10" width="36" height="6" rx="3" fill="#0f9f8c"/>'
                   . '<g fill="' . $f('a') . '"><rect x="12" y="24" width="10" height="9" rx="2.5"/><rect x="27" y="24" width="10" height="9" rx="2.5"/><rect x="42" y="24" width="10" height="9" rx="2.5"/><rect x="12" y="40" width="10" height="9" rx="2.5"/><rect x="42" y="40" width="10" height="9" rx="2.5"/></g>'
                   . '<rect x="27" y="40" width="10" height="9" rx="2.5" fill="' . $f('b') . '"/>';
                break;
            case 'bece': // graduation cap over a certificate
                $d = $lin('a', '#1f3b70', '#0f1f3d') . $lin('b', '#ffcf5c', '#f2a516');
                $g = '<rect x="10" y="30" width="44" height="26" rx="4" fill="' . $f('b') . '"/><rect x="16" y="38" width="22" height="3" rx="1.5" fill="#fff"/><rect x="16" y="45" width="14" height="3" rx="1.5" fill="#fff" opacity=".8"/><circle cx="45" cy="44" r="5" fill="#ef4f62"/>'
                   . '<path d="M32 6l26 10-26 10L6 16z" fill="' . $f('a') . '"/><path d="M18 21v6c0 3 6 5 14 5s14-2 14-5v-6l-14 5.4z" fill="' . $f('a') . '"/><path d="M56 17v10" stroke="#ef4f62" stroke-width="2.4" stroke-linecap="round"/>';
                break;
            case 'calendar': // calendar with a highlighted day
                $d = $lin('a', '#ff7a6b', '#e8453b');
                $g = '<rect x="6" y="10" width="52" height="46" rx="6" fill="#fff" stroke="#f6b4ae" stroke-width="1.5"/><path d="M6 16a6 6 0 016-6h40a6 6 0 016 6v8H6z" fill="' . $f('a') . '"/>'
                   . '<rect x="16" y="5" width="4" height="10" rx="2" fill="#a3261d"/><rect x="44" y="5" width="4" height="10" rx="2" fill="#a3261d"/>'
                   . '<g fill="#fbd0cb"><rect x="13" y="30" width="8" height="7" rx="1.5"/><rect x="25" y="30" width="8" height="7" rx="1.5"/><rect x="13" y="42" width="8" height="7" rx="1.5"/><rect x="37" y="42" width="8" height="7" rx="1.5"/></g>'
                   . '<rect x="37" y="30" width="8" height="7" rx="1.5" fill="' . $f('a') . '"/><rect x="25" y="42" width="8" height="7" rx="1.5" fill="#5aa9ff"/>';
                break;
            case 'fees': // bill with a cedi stamp
                $d = $lin('a', '#5aa9ff', '#2563eb') . $lin('b', '#3ddc84', '#16a34a');
                $g = '<rect x="10" y="6" width="36" height="50" rx="5" fill="' . $f('a') . '"/><rect x="16" y="14" width="18" height="3" rx="1.5" fill="#fff"/>'
                   . '<g fill="#fff" opacity=".75"><rect x="16" y="23" width="24" height="2.6" rx="1.3"/><rect x="16" y="29" width="24" height="2.6" rx="1.3"/><rect x="16" y="35" width="16" height="2.6" rx="1.3"/></g>'
                   . '<circle cx="46" cy="45" r="12" fill="' . $f('b') . '" stroke="#fff" stroke-width="2.5"/><text x="46" y="50.5" text-anchor="middle" font-family="Arial,sans-serif" font-size="14" font-weight="700" fill="#fff">₵</text>';
                break;
            case 'requisition': // clipboard with approval stamp
                $d = $lin('a', '#ffcf5c', '#f2a516') . $lin('b', '#3ddc84', '#16a34a');
                $g = '<rect x="10" y="10" width="38" height="46" rx="5" fill="' . $f('a') . '"/><rect x="15" y="16" width="28" height="35" rx="2.5" fill="#fff"/><rect x="20" y="5" width="18" height="9" rx="3" fill="#7a5410"/>'
                   . '<g fill="#cbd5e1"><rect x="19" y="22" width="20" height="2.6" rx="1.3"/><rect x="19" y="28" width="20" height="2.6" rx="1.3"/><rect x="19" y="34" width="12" height="2.6" rx="1.3"/></g>'
                   . '<circle cx="46" cy="46" r="11" fill="' . $f('b') . '" stroke="#fff" stroke-width="2.5"/><path d="M40.5 46.5l3.5 3.5 6.5-7" stroke="#fff" stroke-width="2.8" fill="none" stroke-linecap="round" stroke-linejoin="round"/>';
                break;
            case 'payroll': // stack of payslips with people
                $d = $lin('a', '#34c58b', '#0f8a5f') . $lin('b', '#5aa9ff', '#2563eb');
                $g = '<rect x="14" y="8" width="40" height="30" rx="4" fill="#9fe6c6"/><rect x="10" y="13" width="40" height="30" rx="4" fill="' . $f('a') . '"/>'
                   . '<circle cx="30" cy="28" r="7" fill="#fff" opacity=".9"/><text x="30" y="32" text-anchor="middle" font-family="Arial,sans-serif" font-size="10" font-weight="700" fill="#0f8a5f">₵</text>'
                   . '<circle cx="20" cy="49" r="5" fill="' . $f('b') . '"/><path d="M12 60c0-5 3.5-7 8-7s8 2 8 7z" fill="' . $f('b') . '"/><circle cx="40" cy="49" r="5" fill="' . $f('b') . '"/><path d="M32 60c0-5 3.5-7 8-7s8 2 8 7z" fill="' . $f('b') . '"/>';
                break;
            case 'resources': // desk and chair with a book
                $d = $lin('a', '#ffb547', '#e2672b') . $lin('b', '#5aa9ff', '#2563eb');
                $g = '<rect x="6" y="26" width="40" height="6" rx="2" fill="' . $f('a') . '"/><rect x="10" y="32" width="4" height="22" rx="1.5" fill="#b84a17"/><rect x="38" y="32" width="4" height="22" rx="1.5" fill="#b84a17"/>'
                   . '<rect x="14" y="16" width="16" height="10" rx="1.5" fill="' . $f('b') . '"/><rect x="21" y="16" width="2" height="10" fill="#fff" opacity=".6"/>'
                   . '<rect x="46" y="20" width="12" height="18" rx="3" fill="#475569"/><rect x="44" y="38" width="16" height="5" rx="2" fill="#64748b"/><rect x="46" y="43" width="3" height="11" fill="#475569"/><rect x="55" y="43" width="3" height="11" fill="#475569"/>';
                break;
            case 'transport': // school bus
                $d = $lin('a', '#ffcf5c', '#f2a516');
                $g = '<rect x="6" y="12" width="52" height="34" rx="7" fill="' . $f('a') . '"/><rect x="10" y="17" width="12" height="11" rx="2" fill="#dbeafe"/><rect x="25" y="17" width="12" height="11" rx="2" fill="#dbeafe"/><rect x="40" y="17" width="14" height="11" rx="2" fill="#dbeafe"/>'
                   . '<rect x="6" y="33" width="52" height="3" fill="#1f2937" opacity=".75"/><rect x="50" y="38" width="6" height="4" rx="1" fill="#fff"/>'
                   . '<circle cx="18" cy="48" r="6" fill="#1f2937"/><circle cx="46" cy="48" r="6" fill="#1f2937"/><circle cx="18" cy="48" r="2.3" fill="#cbd5e1"/><circle cx="46" cy="48" r="2.3" fill="#cbd5e1"/>';
                break;
            case 'canteen': // bowl of food with steam and spoon
                $d = $lin('a', '#ff9a5a', '#e8513b') . $lin('b', '#ffcf5c', '#f59f1b');
                $g = '<path d="M6 30h52a26 22 0 01-52 0z" fill="' . $f('a') . '"/><ellipse cx="32" cy="30" rx="26" ry="5" fill="' . $f('b') . '"/><circle cx="24" cy="29" r="3" fill="#3ddc84"/><circle cx="34" cy="28" r="3" fill="#ef4444"/><circle cx="42" cy="30" r="2.6" fill="#fff"/>'
                   . '<rect x="18" y="52" width="28" height="4" rx="2" fill="#b84a17"/><path d="M22 8c-2 3 2 5 0 8M32 6c-2 3 2 5 0 8M42 8c-2 3 2 5 0 8" stroke="#94a3b8" stroke-width="2.2" fill="none" stroke-linecap="round"/>';
                break;
            case 'notices': // pin board with notes
                $d = $lin('a', '#d9a066', '#a8682e');
                $g = '<rect x="4" y="8" width="56" height="44" rx="5" fill="' . $f('a') . '"/><rect x="8" y="12" width="48" height="36" rx="3" fill="#f3d9b8"/>'
                   . '<rect x="12" y="16" width="18" height="14" rx="1.5" fill="#ffe08a" transform="rotate(-4 21 23)"/><rect x="34" y="16" width="18" height="12" rx="1.5" fill="#bfe3ff" transform="rotate(3 43 22)"/><rect x="20" y="32" width="22" height="12" rx="1.5" fill="#ffc9d6"/>'
                   . '<circle cx="21" cy="17" r="2" fill="#ef4444"/><circle cx="43" cy="17" r="2" fill="#2563eb"/><circle cx="31" cy="33" r="2" fill="#16a34a"/><rect x="28" y="52" width="8" height="6" fill="#a8682e"/>';
                break;
            case 'chat': // two speech bubbles
                $d = $lin('a', '#5aa9ff', '#2563eb') . $lin('b', '#3fd6c0', '#0f9f8c');
                $g = '<path d="M6 12a5 5 0 015-5h28a5 5 0 015 5v16a5 5 0 01-5 5H20l-8 7v-7h-1a5 5 0 01-5-5z" fill="' . $f('a') . '"/><rect x="12" y="15" width="24" height="3" rx="1.5" fill="#fff"/><rect x="12" y="22" width="16" height="3" rx="1.5" fill="#fff" opacity=".8"/>'
                   . '<path d="M58 30a5 5 0 00-5-5H28a5 5 0 00-5 5v14a5 5 0 005 5h18l7 6v-6a5 5 0 005-5z" fill="' . $f('b') . '"/><circle cx="33" cy="37" r="2.4" fill="#fff"/><circle cx="41" cy="37" r="2.4" fill="#fff"/><circle cx="49" cy="37" r="2.4" fill="#fff"/>';
                break;
            case 'sms': // phone with message and bell
                $d = $lin('a', '#2b4c8c', '#14284f') . $lin('b', '#ffcf5c', '#f59f1b');
                $g = '<rect x="14" y="4" width="30" height="56" rx="6" fill="' . $f('a') . '"/><rect x="18" y="11" width="22" height="40" rx="2" fill="#dbeafe"/><circle cx="29" cy="55" r="2" fill="#fff"/>'
                   . '<path d="M21 17h16a2 2 0 012 2v8a2 2 0 01-2 2h-9l-4 4v-4h-3a2 2 0 01-2-2v-8a2 2 0 012-2z" fill="#3b82f6"/>'
                   . '<path d="M50 30a8 8 0 00-8 8v6l-3 4h22l-3-4v-6a8 8 0 00-8-8z" fill="' . $f('b') . '" stroke="#fff" stroke-width="2"/><circle cx="50" cy="51" r="2.6" fill="#f59f1b"/>';
                break;
            case 'forms': // form with checkboxes and a pencil
                $d = $lin('a', '#ffcf5c', '#f2a516') . $lin('b', '#8b7bff', '#5146c9');
                $g = '<rect x="8" y="6" width="38" height="52" rx="5" fill="#fff" stroke="#f5d48a" stroke-width="1.5"/><rect x="8" y="6" width="38" height="10" rx="5" fill="' . $f('a') . '"/>'
                   . '<rect x="14" y="22" width="6" height="6" rx="1.5" fill="none" stroke="#f2a516" stroke-width="1.8"/><path d="M15 25l1.6 1.6 3-3.4" stroke="#f2a516" stroke-width="1.8" fill="none"/><rect x="24" y="23.5" width="16" height="3" rx="1.5" fill="#cbd5e1"/>'
                   . '<rect x="14" y="33" width="6" height="6" rx="1.5" fill="none" stroke="#f2a516" stroke-width="1.8"/><rect x="24" y="34.5" width="16" height="3" rx="1.5" fill="#cbd5e1"/><rect x="14" y="44" width="26" height="6" rx="2" fill="#f1f5f9" stroke="#cbd5e1"/>'
                   . '<g transform="rotate(40 50 38)"><rect x="46" y="18" width="8" height="30" rx="1.5" fill="' . $f('b') . '"/><path d="M46 48h8l-4 7z" fill="#f6dcae"/></g>';
                break;
            case 'staffatt': // person card with sign-in clock
                $d = $lin('a', '#3fd6c0', '#0f9f8c') . $lin('b', '#5aa9ff', '#2563eb');
                $g = '<rect x="6" y="12" width="40" height="42" rx="6" fill="' . $f('a') . '"/><circle cx="26" cy="27" r="7" fill="#fff"/><path d="M14 46c0-7 5-10 12-10s12 3 12 10z" fill="#fff" opacity=".9"/>'
                   . '<circle cx="47" cy="44" r="13" fill="' . $f('b') . '" stroke="#fff" stroke-width="2.5"/><path d="M47 37v7l5 3" stroke="#fff" stroke-width="2.8" fill="none" stroke-linecap="round"/>';
                break;
            case 'setup': // school building with a gear
                $d = $lin('a', '#5aa9ff', '#2563eb') . $lin('b', '#94a3b8', '#475569');
                $g = '<path d="M4 26L26 12l22 14z" fill="#1e40af"/><rect x="8" y="26" width="36" height="28" rx="2" fill="' . $f('a') . '"/><rect x="21" y="38" width="10" height="16" rx="1.5" fill="#1e3a8a"/><rect x="12" y="31" width="7" height="6" rx="1" fill="#dbeafe"/><rect x="33" y="31" width="7" height="6" rx="1" fill="#dbeafe"/>'
                   . '<g transform="translate(47 44)"><circle r="11" fill="' . $f('b') . '" stroke="#fff" stroke-width="2"/><g stroke="#fff" stroke-width="3" stroke-linecap="round"><path d="M0-7v-2M0 7v2M-7 0h-2M7 0h2M-5-5l-1.4-1.4M5 5l1.4 1.4M-5 5l-1.4 1.4M5-5l1.4-1.4"/></g><circle r="4" fill="#fff"/></g>';
                break;
            case 'users': // group of user accounts with a plus
                $d = $lin('a', '#5aa9ff', '#2563eb') . $lin('b', '#3ddc84', '#16a34a');
                $g = '<circle cx="22" cy="20" r="9" fill="' . $f('a') . '"/><path d="M6 50c0-10 7-15 16-15s16 5 16 15z" fill="' . $f('a') . '"/><circle cx="40" cy="22" r="7" fill="#93c5fd"/><path d="M30 48c0-8 5-12 12-12s10 3 12 7" fill="#93c5fd"/>'
                   . '<circle cx="48" cy="46" r="10" fill="' . $f('b') . '" stroke="#fff" stroke-width="2.5"/><path d="M48 41v10M43 46h10" stroke="#fff" stroke-width="2.8" stroke-linecap="round"/>';
                break;
            case 'security': // shield with a padlock
                $d = $lin('a', '#2b4c8c', '#14284f') . $lin('b', '#ffcf5c', '#f59f1b');
                $g = '<path d="M32 4l24 9v16c0 15-10 26-24 31C18 55 8 44 8 29V13z" fill="' . $f('a') . '"/><path d="M32 9l19 7v13c0 12-8 21-19 25z" fill="#fff" opacity=".08"/>'
                   . '<rect x="21" y="28" width="22" height="18" rx="3" fill="' . $f('b') . '"/><path d="M25 28v-5a7 7 0 0114 0v5" stroke="' . $f('b') . '" stroke-width="4" fill="none"/><circle cx="32" cy="36" r="3" fill="#7a5410"/><rect x="31" y="37" width="2" height="5" fill="#7a5410"/>';
                break;
            case 'logins': // key with a parent/child card
                $d = $lin('a', '#ff8fa3', '#e8456b') . $lin('b', '#ffcf5c', '#f2a516');
                $g = '<rect x="6" y="10" width="36" height="26" rx="5" fill="' . $f('a') . '"/><circle cx="17" cy="21" r="4.5" fill="#fff"/><path d="M10 31c0-4 3-6 7-6s7 2 7 6z" fill="#fff" opacity=".9"/><rect x="27" y="17" width="10" height="3" rx="1.5" fill="#fff"/><rect x="27" y="24" width="7" height="3" rx="1.5" fill="#fff" opacity=".7"/>'
                   . '<circle cx="42" cy="44" r="9" fill="none" stroke="' . $f('b') . '" stroke-width="5"/><path d="M34 50L16 58M22 55l-3-5M27 53l-2-4" stroke="' . $f('b') . '" stroke-width="5" stroke-linecap="round"/>';
                break;
            case 'backup': // cloud with a safe box
                $d = $lin('a', '#93c5fd', '#3b82f6') . $lin('b', '#475569', '#1f2937');
                $g = '<path d="M16 38a10 10 0 01-1-20 14 14 0 0127-4 10 10 0 0110 16 8 8 0 01-4 8z" fill="' . $f('a') . '"/>'
                   . '<rect x="18" y="30" width="28" height="26" rx="4" fill="' . $f('b') . '"/><circle cx="32" cy="43" r="6" fill="none" stroke="#ffcf5c" stroke-width="2.5"/><path d="M32 39v4l3 2" stroke="#ffcf5c" stroke-width="2" fill="none" stroke-linecap="round"/><rect x="21" y="54" width="5" height="4" rx="1" fill="#1f2937"/><rect x="38" y="54" width="5" height="4" rx="1" fill="#1f2937"/>';
                break;
            case 'phoneapp': // phone with app grid and notification dot
                $d = $lin('a', '#8b7bff', '#5146c9');
                $g = '<rect x="16" y="4" width="32" height="56" rx="7" fill="' . $f('a') . '"/><rect x="20" y="11" width="24" height="40" rx="3" fill="#ede9fe"/><circle cx="32" cy="55" r="2" fill="#fff"/>'
                   . '<g><rect x="23" y="15" width="8" height="8" rx="2" fill="#5aa9ff"/><rect x="33" y="15" width="8" height="8" rx="2" fill="#3fd6c0"/><rect x="23" y="26" width="8" height="8" rx="2" fill="#ffcf5c"/><rect x="33" y="26" width="8" height="8" rx="2" fill="#ff8fa3"/><rect x="23" y="37" width="8" height="8" rx="2" fill="#3ddc84"/><rect x="33" y="37" width="8" height="8" rx="2" fill="#8b7bff"/></g>'
                   . '<circle cx="47" cy="8" r="6" fill="#ef4444" stroke="#fff" stroke-width="2"/>';
                break;
            case 'audit': // log book with magnifier
                $d = $lin('a', '#5aa9ff', '#2563eb') . $lin('b', '#ffcf5c', '#f59f1b');
                $g = '<rect x="8" y="6" width="38" height="50" rx="5" fill="' . $f('a') . '"/><rect x="8" y="6" width="6" height="50" rx="2" fill="#1e3a8a"/>'
                   . '<g fill="#fff" opacity=".8"><rect x="19" y="14" width="20" height="3" rx="1.5"/><rect x="19" y="22" width="20" height="3" rx="1.5"/><rect x="19" y="30" width="14" height="3" rx="1.5"/></g>'
                   . '<circle cx="44" cy="42" r="9" fill="#fff" stroke="' . $f('b') . '" stroke-width="4"/><path d="M50.5 48.5l7 7" stroke="' . $f('b') . '" stroke-width="5" stroke-linecap="round"/>';
                break;
            case 'whatsapp': // generic green chat bubble with message dots (not the WhatsApp logo)
                $d = $lin('a', '#4ade80', '#16a34a');
                $g = '<path d="M8 14a6 6 0 016-6h36a6 6 0 016 6v24a6 6 0 01-6 6H26l-11 10v-10h-1a6 6 0 01-6-6z" fill="' . $f('a') . '"/>'
                   . '<circle cx="22" cy="26" r="3.6" fill="#fff"/><circle cx="32" cy="26" r="3.6" fill="#fff"/><circle cx="42" cy="26" r="3.6" fill="#fff"/>'
                   . '<path d="M46 50l4 4 8-8" stroke="#16a34a" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round"/><path d="M40 50l4 4" stroke="#16a34a" stroke-width="3" stroke-linecap="round" opacity=".55"/>';
                break;
            case 'dashboard': // tiles of a dashboard
                $d = $lin('a', '#5aa9ff', '#2563eb') . $lin('b', '#3fd6c0', '#0f9f8c') . $lin('c', '#ffcf5c', '#f59f1b') . $lin('e', '#ff8fa3', '#e8456b');
                $g = '<rect x="6" y="6" width="24" height="30" rx="5" fill="' . $f('a') . '"/><rect x="34" y="6" width="24" height="18" rx="5" fill="' . $f('b') . '"/><rect x="34" y="28" width="24" height="30" rx="5" fill="' . $f('c') . '"/><rect x="6" y="40" width="24" height="18" rx="5" fill="' . $f('e') . '"/>'
                   . '<path d="M11 28l5-6 4 4 6-9" stroke="#fff" stroke-width="2.6" fill="none" stroke-linecap="round" stroke-linejoin="round"/><circle cx="46" cy="15" r="5" fill="#fff" opacity=".9"/><rect x="39" y="45" width="14" height="3" rx="1.5" fill="#fff" opacity=".8"/><rect x="39" y="37" width="10" height="3" rx="1.5" fill="#fff" opacity=".8"/>';
                break;
            case 'engine': // launcher grid of coloured tiles
                $d = $lin('a', '#5aa9ff', '#2563eb') . $lin('b', '#3fd6c0', '#0f9f8c') . $lin('c', '#ffcf5c', '#f59f1b') . $lin('e', '#ff8fa3', '#e8456b');
                $g = '<rect x="6" y="6" width="23" height="23" rx="6" fill="' . $f('a') . '"/><rect x="35" y="6" width="23" height="23" rx="6" fill="' . $f('b') . '"/><rect x="6" y="35" width="23" height="23" rx="6" fill="' . $f('c') . '"/><rect x="35" y="35" width="23" height="23" rx="6" fill="' . $f('e') . '"/>';
                break;
            default:
                $d = '';
                $g = '<circle cx="32" cy="32" r="20" fill="#94a3b8"/>';
        }
        return '<svg class="eng-svg" viewBox="0 0 64 64" width="' . $size . '" height="' . $size . '" aria-hidden="true" focusable="false"><defs>' . $d . '</defs>' . $g . '</svg>';
    }
}
