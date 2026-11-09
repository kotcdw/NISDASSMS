<?php
declare(strict_types=1);

/** Fixed asset register with straight-line depreciation (worked out monthly from the purchase date). */
final class Assets
{
    /** category => [label, default useful life in years (0 = not depreciated)] */
    public const CATEGORIES = [
        'land' => ['Land', 0], 'buildings' => ['Buildings', 40], 'furniture' => ['Furniture & fittings', 10], 'ict' => ['Computers & ICT', 4],
        'vehicles' => ['Vehicles (bus …)', 5], 'equipment' => ['Lab, kitchen & other equipment', 5],
    ];
    public const FUNDS = ['school' => 'School funds', 'pta' => 'PTA', 'donation' => 'Donation / gift', 'getfund' => 'GETFund / Government', 'church' => 'Church / Conference'];
    public const CONDITION = ['good' => ['Good', 'good'], 'fair' => ['Fair', 'warn'], 'poor' => ['Poor', 'bad']];
    public const STATUS = ['in_use' => ['In use', 'good'], 'repair' => ['Under repair', 'warn'], 'disposed' => ['Disposed / sold', 'neutral'], 'lost' => ['Lost / stolen', 'bad']];

    /** Depreciation figures as at a date: annual charge, accumulated, net book value, charge for a given year. */
    public static function value(array $a, ?string $asAt = null, ?int $year = null): array
    {
        $asAt = $asAt ?: date('Y-m-d');
        $cost = (float) $a['cost'];
        $base = max(0.0, $cost - (float) $a['residual']);
        $life = (int) $a['life_years'];
        $annual = $life > 0 ? $base / $life : 0.0;
        $end = in_array($a['status'], ['disposed', 'lost'], true) && $a['disposed_on'] && $a['disposed_on'] < $asAt ? $a['disposed_on'] : $asAt;
        $months = function (string $to) use ($a): int {
            if ($to < $a['purchase_date']) {
                return 0;
            }
            [$y1, $m1] = array_map('intval', explode('-', substr($a['purchase_date'], 0, 7)));
            [$y2, $m2] = array_map('intval', explode('-', substr($to, 0, 7)));
            return ($y2 - $y1) * 12 + ($m2 - $m1) + 1; // the month of purchase counts
        };
        $acc = fn(string $to) => min($base, $annual * $months($to) / 12);
        $accum = round($acc($end), 2);
        $charge = null;
        if ($year) {
            $charge = round(max(0.0, $acc(min($end, $year . '-12-31')) - $acc(min($end, ($year - 1) . '-12-31'))), 2);
        }
        return ['annual' => round($annual, 2), 'accum' => $accum, 'nbv' => round($cost - $accum, 2), 'charge' => $charge];
    }

    public static function log(int $assetId, string $action, string $note = ''): void
    {
        db()->insert('asset_log', ['asset_id' => $assetId, 'action' => $action, 'note' => mb_substr($note, 0, 255) ?: null, 'user_id' => Auth::id()]);
    }
}
