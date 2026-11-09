<?php
declare(strict_types=1);

final class CalendarEvent
{
    /** key => [label, css colour var used in legend] */
    public const CATEGORIES = [
        'term'     => ['Term dates', '#1f4e8c'],
        'holiday'  => ['Public holiday / break', '#b42318'],
        'exam'     => ['Exams & assessment', '#7a3db8'],
        'pta'      => ['PTA / parents', '#0f766e'],
        'sda'      => ['SDA church programme', '#9a6700'],
        'sports'   => ['Sports & culture', '#15803d'],
        'deadline' => ['Fees / deadlines', '#c2410c'],
        'staff'    => ['Staff meeting / INSET', '#334155'],
        'event'    => ['School event', '#475467'],
    ];

    public const AUDIENCES = [
        'all'     => 'Everyone',
        'staff'   => 'Staff only',
        'parents' => 'Parents & students',
        'level'   => 'One level only',
        'class'   => 'One class only',
    ];

    public static function label(string $cat): string
    {
        return self::CATEGORIES[$cat][0] ?? ucfirst($cat);
    }

    /** True if any day in [start, end] is a Saturday (Sabbath). */
    public static function touchesSabbath(string $start, string $end): bool
    {
        $d = new DateTime($start);
        $last = new DateTime($end);
        for ($i = 0; $d <= $last && $i < 400; $i++) {
            if ((int) $d->format('N') === 6) {
                return true;
            }
            $d->modify('+1 day');
        }
        return false;
    }

    /**
     * Visibility filter for the current user: staff see everything; portal users see public, level and class events
     * for their own class or their children's classes.
     */
    public static function visibilitySql(string $t = ''): string
    {
        if (can('staff')) {
            return '1=1';
        }
        $levels = [];
        $classes = [];
        if (Auth::role() === 'parent') {
            foreach (Accounts::childrenOf((int) Auth::id()) as $c) {
                if ($c['status'] === 'active' && $c['level_id']) {
                    $levels[] = (int) $c['level_id'];
                    if ($c['class_id']) {
                        $classes[] = (int) $c['class_id'];
                    }
                }
            }
        } elseif (Auth::role() === 'pupil' && ($p = Accounts::pupilOf((int) Auth::id())) && $p['class_id']) {
            $levels[] = (int) db()->value('SELECT level_id FROM classes WHERE id = ?', [$p['class_id']]);
            $classes[] = (int) $p['class_id'];
        }
        $levels = array_values(array_unique(array_filter($levels)));
        $classes = array_values(array_unique(array_filter($classes)));
        // $t = table alias with its dot (e.g. 'e.') when the query joins levels/classes, which also have these columns
        return "({$t}audience IN ('all','parents')"
            . ($levels ? " OR ({$t}audience = 'level' AND {$t}level_id IN (" . implode(',', $levels) . '))' : '')
            . ($classes ? " OR ({$t}audience = 'class' AND {$t}class_id IN (" . implode(',', $classes) . '))' : '') . ')';
    }
}
