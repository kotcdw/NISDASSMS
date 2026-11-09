<?php
declare(strict_types=1);

/**
 * Small line icons for the side menu and the top bar (24×24, drawn with the current text colour).
 * Icon keys are the ones Menu::items() uses; unknown keys fall back to a plain dot.
 */
final class Icons
{
    private const PATHS = [
        'dashboard'   => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v10h5v-6h4v6h5V10"/>',
        'apps'        => '<rect x="4" y="4" width="6.5" height="6.5" rx="1.5"/><rect x="13.5" y="4" width="6.5" height="6.5" rx="1.5"/><rect x="4" y="13.5" width="6.5" height="6.5" rx="1.5"/><rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1.5"/>',
        'sis'         => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 19c.6-3.2 2.8-5 5.5-5s4.9 1.8 5.5 5"/><circle cx="17" cy="9" r="2.4"/><path d="M15.8 14.2c2.3.2 3.9 1.8 4.4 4.3"/>',
        'users'       => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 19c.6-3.2 2.8-5 5.5-5s4.9 1.8 5.5 5"/><circle cx="17" cy="9" r="2.4"/><path d="M15.8 14.2c2.3.2 3.9 1.8 4.4 4.3"/>',
        'admission'   => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 3h6v3H9z"/><path d="M12 10v6M9 13h6"/>',
        'crm'         => '<path d="M4 6h16v10H9l-4 3.5V16H4z"/><path d="M8 10h8M8 13h5"/>',
        'attendance'  => '<rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 9.5h17M8 3v4M16 3v4"/><path d="m8.5 14.5 2.3 2.2 4.7-4.7"/>',
        'leave'       => '<path d="M13 4H6v16h7"/><path d="M10 12h10M16.5 8.5 20 12l-3.5 3.5"/>',
        'health'      => '<path d="M12 20s-7.5-4.4-7.5-10A4.2 4.2 0 0 1 12 7.6 4.2 4.2 0 0 1 19.5 10c0 5.6-7.5 10-7.5 10z"/><path d="M12 10.5v5M9.5 13h5"/>',
        'assignment'  => '<path d="M14 4H6v16h12V8z"/><path d="M14 4v4h4"/><path d="M9 13h6M9 16.5h4"/>',
        'lms'         => '<path d="M4 5.5C6.5 4.5 9.5 4.5 12 6c2.5-1.5 5.5-1.5 8-.5V19c-2.5-1-5.5-1-8 .5-2.5-1.5-5.5-1.5-8-.5z"/><path d="M12 6v13.5"/>',
        'course'      => '<path d="M4 5.5C6.5 4.5 9.5 4.5 12 6c2.5-1.5 5.5-1.5 8-.5V19c-2.5-1-5.5-1-8 .5-2.5-1.5-5.5-1.5-8-.5z"/><path d="M12 6v13.5"/>',
        'live'        => '<rect x="3" y="6.5" width="12.5" height="11" rx="2"/><path d="m15.5 10.5 5.5-3v9l-5.5-3"/>',
        'quiz'        => '<circle cx="12" cy="12" r="8.5"/><path d="M9.6 9.5a2.5 2.5 0 1 1 3.4 2.3c-.6.3-1 .8-1 1.5v.6"/><path d="M12 16.8v.1"/>',
        'gradebook'   => '<path d="M4 20h16"/><rect x="5.5" y="11" width="3" height="7"/><rect x="10.5" y="7" width="3" height="11"/><rect x="15.5" y="4" width="3" height="14"/>',
        'exam'        => '<path d="M14 4H6v16h12V8z"/><path d="M14 4v4h4"/><path d="m8.5 14 2 2 4-4"/>',
        'seating'     => '<rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/>',
        'bece'        => '<circle cx="12" cy="9" r="5"/><path d="m9 13.5-1.5 7 4.5-2.5 4.5 2.5-1.5-7"/>',
        'timetable'   => '<rect x="3.5" y="4.5" width="17" height="15" rx="2"/><path d="M3.5 9h17M9 9v10.5M14.5 9v10.5M3.5 14h17"/>',
        'calendar'    => '<rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 9.5h17M8 3v4M16 3v4"/><path d="M8 13h2M12 13h2M16 13h.5M8 16.5h2M12 16.5h2"/>',
        'event'       => '<path d="M5 21V4"/><path d="M5 4.5h12l-2.5 4 2.5 4H5"/>',
        'appointment' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'timesheet'   => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'fees'        => '<rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2.6"/><path d="M6.5 9.5v.1M17.5 14.5v.1"/>',
        'financial'   => '<path d="M5 4h11l3 3v13H5z"/><path d="M8.5 9h7M8.5 12.5h7M8.5 16h4"/>',
        'requisition' => '<path d="M3.5 5h2.5l2 10h10l2-7H7"/><circle cx="9.5" cy="19" r="1.3"/><circle cx="16.5" cy="19" r="1.3"/>',
        'payroll'     => '<path d="M4 7.5h14a2 2 0 0 1 2 2V18a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/><path d="M4 7.5 15 4l1 3.5"/><path d="M15.5 13.5h4.5"/>',
        'resources'   => '<path d="M4 20V9l8-5 8 5v11"/><path d="M9 20v-6h6v6M3 20h18"/>',
        'document'    => '<path d="M14 4H6v16h12V8z"/><path d="M14 4v4h4"/><path d="M9 12.5h6M9 16h6"/>',
        'library'     => '<path d="M5 4v16M9 4v16"/><path d="m13 5.5 3.5-1 3.5 15-3.5 1z"/><path d="M3.5 20h17"/>',
        'transport'   => '<rect x="4.5" y="4" width="15" height="13" rx="2.5"/><path d="M4.5 11h15M8 17v2.5M16 17v2.5"/><path d="M8 14.2v.1M16 14.2v.1"/>',
        'canteen'     => '<path d="M5 9h12v4a6 6 0 0 1-12 0z"/><path d="M17 10h1.5a2 2 0 0 1 0 4H17"/><path d="M8 4.5v2M11 4v2.5M14 4.5v2"/>',
        'notices'     => '<path d="M4 10v4h3l7 4V6l-7 4z"/><path d="M17.5 9.5a3.5 3.5 0 0 1 0 5"/>',
        'chat'        => '<path d="M4 5.5h16v10.5H10l-4.5 3.5V16H4z"/>',
        'sms'         => '<rect x="6.5" y="3" width="11" height="18" rx="2"/><path d="M10.5 18h3"/><path d="M9.5 8h5M9.5 11h3.5"/>',
        'forms'       => '<rect x="4.5" y="4" width="15" height="16" rx="2"/><path d="m8 9 1.3 1.3L11.5 8M13.5 9.5H16M8 14.5h.01M10.5 14.5H16"/>',
        'staffatt'    => '<circle cx="10" cy="8" r="3.3"/><path d="M4 19.5c.6-3.3 3-5.2 6-5.2 1.3 0 2.4.3 3.4.9"/><path d="m15 17.5 2 2 3.5-3.8"/>',
        'audit'       => '<path d="M12 3.5 5 6v5.5c0 4.4 3 7.5 7 9 4-1.5 7-4.6 7-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'setup'       => '<circle cx="12" cy="12" r="3"/><path d="M12 3v2.5M12 18.5V21M3 12h2.5M18.5 12H21M5.6 5.6l1.8 1.8M16.6 16.6l1.8 1.8M5.6 18.4l1.8-1.8M16.6 7.4l1.8-1.8"/>',
        'security'    => '<rect x="5" y="10.5" width="14" height="10" rx="2"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/><path d="M12 14.5v2.5"/>',
        'logins'      => '<circle cx="8" cy="12" r="3.5"/><path d="M11.5 12H21M17.5 12v3M20.5 12v2.5"/>',
        'backup'      => '<ellipse cx="12" cy="6" rx="7" ry="2.5"/><path d="M5 6v12c0 1.4 3.1 2.5 7 2.5s7-1.1 7-2.5V6"/><path d="M5 12c0 1.4 3.1 2.5 7 2.5s7-1.1 7-2.5"/>',
        'phoneapp'    => '<rect x="6.5" y="3" width="11" height="18" rx="2"/><path d="M10.5 18h3"/>',
        'bell'        => '<path d="M6 16.5V11a6 6 0 0 1 12 0v5.5l1.5 1.5h-15z"/><path d="M10 20.5a2 2 0 0 0 4 0"/>',
        'alert'       => '<path d="M12 4 2.8 19.5h18.4z"/><path d="M12 10v4.5M12 17v.1"/>',
        'menu'        => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close'       => '<path d="M6 6l12 12M18 6 6 18"/>',
        'rail'        => '<rect x="3.5" y="4.5" width="17" height="15" rx="2"/><path d="M9 4.5v15"/><path d="m15.5 10-2 2 2 2"/>',
        'chevron'     => '<path d="m9 6 6 6-6 6"/>',
        'down'        => '<path d="m6 9 6 6 6-6"/>',
        'clock'       => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
    ];

    /** Inline SVG icon; decorative (hidden from screen readers) — the link or button carries the words. */
    public static function svg(string $key, int $size = 20, string $class = 'ico'): string
    {
        $p = self::PATHS[$key] ?? '<circle cx="12" cy="12" r="3"/>';
        return '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $p . '</svg>';
    }
}
