<?php
declare(strict_types=1);

/**
 * Small server-drawn SVG charts (no JavaScript, works offline and under the strict CSP).
 * One series per chart in the school's brand colour; hovering a bar or point shows its value
 * (SVG <title>); every chart is accompanied by its numbers in a table or labels.
 */
final class Charts
{
    /**
     * Vertical bar chart. $rows = [[label, value, tooltip?], ...]
     * Options: height, format (callable), max, target (dashed reference line value), soft (array of indexes drawn lighter), labelEvery.
     */
    public static function bars(array $rows, array $o = []): string
    {
        if (!$rows) {
            return '<p class="muted">No data yet.</p>';
        }
        $w = (int) ($o['width'] ?? 640);
        $h = (int) ($o['height'] ?? 200);
        $padL = 36;
        $padB = 34;
        $padT = 16;
        $fmt = $o['format'] ?? fn($v) => number_format((float) $v);
        $max = (float) ($o['max'] ?? 0) ?: max(1.0, ...array_map(fn($r) => (float) $r[1], $rows));
        $max = self::nice($max);
        $n = count($rows);
        $slot = ($w - $padL) / $n;
        $bw = max(4.0, min(42.0, $slot * 0.62));
        $plotH = $h - $padB - $padT;
        $svg = '<svg class="chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . e($o['label'] ?? 'Bar chart') . '">';
        foreach ([0, 0.5, 1] as $g) {
            $y = $padT + $plotH * (1 - $g);
            $svg .= '<line class="grid" x1="' . $padL . '" x2="' . $w . '" y1="' . $y . '" y2="' . $y . '"/>';
            $svg .= '<text class="axis" x="' . ($padL - 6) . '" y="' . ($y + 4) . '" text-anchor="end">' . e(self::short($max * $g)) . '</text>';
        }
        if (isset($o['target'])) {
            $ty = $padT + $plotH * (1 - min(1, (float) $o['target'] / $max));
            $svg .= '<line class="target" x1="' . $padL . '" x2="' . $w . '" y1="' . $ty . '" y2="' . $ty . '"/>';
        }
        $every = (int) ($o['labelEvery'] ?? max(1, (int) ceil($n / 14)));
        $soft = array_flip($o['soft'] ?? []);
        foreach ($rows as $i => $r) {
            $v = max(0.0, (float) $r[1]);
            $bh = $plotH * min(1, $v / $max);
            $x = $padL + $slot * $i + ($slot - $bw) / 2;
            $y = $padT + $plotH - $bh;
            $tip = $r[2] ?? ($r[0] . ': ' . $fmt($v));
            $rad = min(4, $bw / 2, $bh);
            // Bar with rounded top corners, anchored to the baseline
            $path = $bh > 0
                ? 'M' . $x . ',' . ($padT + $plotH) . 'V' . ($y + $rad) . 'Q' . $x . ',' . $y . ' ' . ($x + $rad) . ',' . $y . 'H' . ($x + $bw - $rad) . 'Q' . ($x + $bw) . ',' . $y . ' ' . ($x + $bw) . ',' . ($y + $rad) . 'V' . ($padT + $plotH) . 'Z'
                : '';
            // Invisible full-height hit area so small bars are easy to hover
            $svg .= '<g><title>' . e($tip) . '</title><rect x="' . ($padL + $slot * $i) . '" y="' . $padT . '" width="' . $slot . '" height="' . $plotH . '" fill="transparent"/>';
            if ($path) {
                $svg .= '<path class="' . (isset($soft[$i]) ? 'bar-soft' : 'bar') . '" d="' . $path . '"/>';
            }
            $svg .= '</g>';
            if ($i % $every === 0) {
                $svg .= '<text class="axis" x="' . ($padL + $slot * $i + $slot / 2) . '" y="' . ($h - $padB + 16) . '" text-anchor="middle">' . e(mb_strimwidth((string) $r[0], 0, 10, '…')) . '</text>';
            }
        }
        return $svg . '</svg>';
    }

    /** Line chart for a trend. $rows = [[label, value|null, tooltip?], ...]; null = gap. Options as bars(), plus min. */
    public static function line(array $rows, array $o = []): string
    {
        $vals = array_filter(array_map(fn($r) => $r[1], $rows), fn($v) => $v !== null);
        if (!$vals) {
            return '<p class="muted">No data yet.</p>';
        }
        $w = (int) ($o['width'] ?? 640);
        $h = (int) ($o['height'] ?? 200);
        $padL = 36;
        $padB = 34;
        $padT = 16;
        $fmt = $o['format'] ?? fn($v) => number_format((float) $v);
        $max = (float) ($o['max'] ?? self::nice(max($vals)));
        $min = (float) ($o['min'] ?? 0);
        $n = count($rows);
        $plotH = $h - $padB - $padT;
        $step = $n > 1 ? ($w - $padL - 12) / ($n - 1) : 0;
        $xy = fn($i, $v) => [$padL + 6 + $step * $i, $padT + $plotH * (1 - (((float) $v - $min) / max(0.0001, $max - $min)))];
        $svg = '<svg class="chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . e($o['label'] ?? 'Line chart') . '">';
        foreach ([0, 0.5, 1] as $g) {
            $y = $padT + $plotH * (1 - $g);
            $svg .= '<line class="grid" x1="' . $padL . '" x2="' . $w . '" y1="' . $y . '" y2="' . $y . '"/>';
            $svg .= '<text class="axis" x="' . ($padL - 6) . '" y="' . ($y + 4) . '" text-anchor="end">' . e(self::short($min + ($max - $min) * $g)) . '</text>';
        }
        if (isset($o['target'])) {
            [, $ty] = $xy(0, $o['target']);
            $svg .= '<line class="target" x1="' . $padL . '" x2="' . $w . '" y1="' . $ty . '" y2="' . $ty . '"/>';
        }
        $d = '';
        $pen = false;
        foreach ($rows as $i => $r) {
            if ($r[1] === null) {
                $pen = false;
                continue;
            }
            [$x, $y] = $xy($i, $r[1]);
            $d .= ($pen ? 'L' : 'M') . round($x, 1) . ',' . round($y, 1);
            $pen = true;
        }
        $svg .= '<path class="line" d="' . $d . '"/>';
        $every = (int) ($o['labelEvery'] ?? max(1, (int) ceil($n / 10)));
        foreach ($rows as $i => $r) {
            [$x] = $xy($i, $min);
            if ($i % $every === 0 || $i === $n - 1) {
                $svg .= '<text class="axis" x="' . $x . '" y="' . ($h - $padB + 16) . '" text-anchor="middle">' . e((string) $r[0]) . '</text>';
            }
            if ($r[1] === null) {
                continue;
            }
            [$x, $y] = $xy($i, $r[1]);
            $svg .= '<g><title>' . e($r[2] ?? ($r[0] . ': ' . $fmt($r[1]))) . '</title><circle cx="' . $x . '" cy="' . $y . '" r="12" fill="transparent"/><circle class="dot" cx="' . $x . '" cy="' . $y . '" r="4"/></g>';
        }
        // Label only the latest value
        $lastI = array_key_last(array_filter($rows, fn($r) => $r[1] !== null));
        [$lx, $ly] = $xy($lastI, $rows[$lastI][1]);
        $svg .= '<text class="val" x="' . min($w - 4, $lx) . '" y="' . max(12, $ly - 9) . '" text-anchor="end">' . e($fmt($rows[$lastI][1])) . '</text>';
        return $svg . '</svg>';
    }

    /**
     * Horizontal bar list for categories (one row per item, label left, value right).
     * $rows = [[label, value, link?, shown text?], ...]. Options: max, target (marker line), format.
     */
    public static function hbars(array $rows, array $o = []): string
    {
        if (!$rows) {
            return '<p class="muted">No data yet.</p>';
        }
        $fmt = $o['format'] ?? fn($v) => number_format((float) $v);
        $max = (float) ($o['max'] ?? 0) ?: max(1.0, ...array_map(fn($r) => (float) $r[1], $rows));
        $out = '<div class="hbars">';
        foreach ($rows as $r) {
            $v = max(0.0, (float) $r[1]);
            $text = $r[3] ?? $fmt($v);
            $label = e((string) $r[0]);
            $out .= '<div class="hbar" title="' . e($r[0] . ': ' . $text) . '"><span class="hbar-l">' . (!empty($r[2]) ? '<a href="' . e($r[2]) . '">' . $label . '</a>' : $label) . '</span>'
                . '<span class="hbar-t"><span class="hbar-f" style="width:' . round(min(100, $v * 100 / $max), 1) . '%"></span>'
                . (isset($o['target']) ? '<span class="hbar-m" style="left:' . round(min(100, (float) $o['target'] * 100 / $max), 1) . '%"></span>' : '')
                . '</span><span class="hbar-v">' . e((string) $text) . '</span></div>';
        }
        return $out . '</div>';
    }

    /**
     * Line chart with several series on one axis. $labels = x labels; $series = [[name, css class, [values|null…]], …].
     * Options: width, height, format (callable), max, min, pointLabel (callable(seriesIndex, i, value) → ?string).
     * The legend is drawn by the caller in HTML.
     */
    public static function lines(array $labels, array $series, array $o = []): string
    {
        $all = [];
        foreach ($series as $se) {
            foreach ($se[2] as $v) {
                if ($v !== null) {
                    $all[] = (float) $v;
                }
            }
        }
        if (!$all) {
            return '<p class="muted">No data yet.</p>';
        }
        $w = (int) ($o['width'] ?? 640);
        $h = (int) ($o['height'] ?? 240);
        $padL = 48;
        $padR = 30;
        $padB = 34;
        $padT = 30;
        $fmt = $o['format'] ?? fn($v) => number_format((float) $v);
        $max = self::nice((float) ($o['max'] ?? max($all)) * 1.08);
        $min = (float) ($o['min'] ?? 0);
        $n = count($labels);
        $plotH = $h - $padB - $padT;
        $inset = 30; // keep the first and last points (and their labels) clear of the axis
        $step = $n > 1 ? ($w - $padL - $padR - 2 * $inset) / ($n - 1) : 0;
        $x0 = $n > 1 ? $padL + $inset : ($w + $padL - $padR) / 2;
        $xy = fn($i, $v) => [round($x0 + $step * $i, 1), round($padT + $plotH * (1 - (((float) $v - $min) / max(0.0001, $max - $min))), 1)];
        $svg = '<svg class="chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . e($o['label'] ?? 'Line chart') . '">';
        foreach ([0, 0.25, 0.5, 0.75, 1] as $g) {
            $y = $padT + $plotH * (1 - $g);
            $svg .= '<line class="grid" x1="' . $padL . '" x2="' . ($w - 8) . '" y1="' . $y . '" y2="' . $y . '"/>';
            $svg .= '<text class="axis" x="' . ($padL - 8) . '" y="' . ($y + 4) . '" text-anchor="end">' . e(self::short($min + ($max - $min) * $g)) . '</text>';
        }
        foreach ($labels as $i => $l) {
            [$x] = $xy($i, $min);
            $svg .= '<text class="axis" x="' . $x . '" y="' . ($h - $padB + 20) . '" text-anchor="middle">' . e((string) $l) . '</text>';
        }
        foreach ($series as $si => [$name, $cls, $vals]) {
            $d = '';
            $pen = false;
            foreach ($vals as $i => $v) {
                if ($v === null) {
                    $pen = false;
                    continue;
                }
                [$x, $y] = $xy($i, $v);
                $d .= ($pen ? 'L' : 'M') . $x . ',' . $y;
                $pen = true;
            }
            $svg .= '<path class="mline ' . e($cls) . '" d="' . $d . '"/>';
            foreach ($vals as $i => $v) {
                if ($v === null) {
                    continue;
                }
                [$x, $y] = $xy($i, $v);
                $svg .= '<g><title>' . e($name . ' — ' . $labels[$i] . ': ' . $fmt($v)) . '</title><circle cx="' . $x . '" cy="' . $y . '" r="12" fill="transparent"/><circle class="mdot ' . e($cls) . '" cx="' . $x . '" cy="' . $y . '" r="5"/></g>';
                $pl = isset($o['pointLabel']) ? ($o['pointLabel'])($si, $i, $v) : null;
                if ($pl !== null && $pl !== '') {
                    $up = $si === 0;
                    $svg .= '<text class="plabel ' . e($cls) . '" x="' . $x . '" y="' . ($up ? $y - 12 : $y + 22) . '" text-anchor="middle">' . e($pl) . '</text>';
                }
            }
        }
        return $svg . '</svg>';
    }

    /** Tiny trend line with a soft area under it (no axes). $vals = numbers. */
    public static function spark(array $vals, int $w = 220, int $h = 60): string
    {
        $vals = array_values(array_map('floatval', $vals));
        if (count($vals) < 2) {
            return '';
        }
        $mn = min($vals);
        $mx = max($vals);
        $n = count($vals);
        $pts = [];
        foreach ($vals as $i => $v) {
            $pts[] = round(4 + ($w - 8) * $i / ($n - 1), 1) . ',' . round(4 + ($h - 8) * (1 - ($mx > $mn ? ($v - $mn) / ($mx - $mn) : 0.5)), 1);
        }
        return '<svg class="spark" viewBox="0 0 ' . $w . ' ' . $h . '" aria-hidden="true"><path class="spark-area" d="M' . implode(' L', $pts) . ' L' . ($w - 4) . ',' . $h . ' L4,' . $h . ' Z"/><path class="spark-line" d="M' . implode(' L', $pts) . '"/></svg>';
    }

    private static function nice(float $v): float
    {
        if ($v <= 0) {
            return 1;
        }
        $p = 10 ** floor(log10($v));
        foreach ([1, 2, 2.5, 5, 10] as $m) {
            if ($v <= $m * $p) {
                return $m * $p;
            }
        }
        return 10 * $p;
    }

    private static function short(float $v): string
    {
        if ($v >= 1000000) {
            return round($v / 1000000, 1) . 'm';
        }
        if ($v >= 1000) {
            return round($v / 1000, 1) . 'k';
        }
        return (string) round($v, $v < 10 ? 1 : 0);
    }
}
