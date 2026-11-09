<?php
declare(strict_types=1);

/** Canteen: items, wallets, counter sales, meal plans, menu. Money taken at the counter goes into the cash book. */
final class Canteen
{
    public const CATEGORIES = ['meal' => 'Meal', 'snack' => 'Snack', 'drink' => 'Drink', 'other' => 'Other'];
    public const MEALS = ['breakfast' => 'Breakfast', 'lunch' => 'Lunch', 'snack' => 'Snack'];

    public static function items(bool $activeOnly = true): array
    {
        return db()->all('SELECT * FROM canteen_items' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY FIELD(category, "meal","snack","drink","other"), name');
    }

    public static function balance(int $pupilId): float
    {
        return (float) (db()->value('SELECT balance FROM canteen_wallets WHERE pupil_id = ?', [$pupilId]) ?? 0);
    }

    /** Pupil with current class, allergies and wallet. */
    public static function pupil(int $id): ?array
    {
        return db()->one(
            "SELECT p.id, p.first_name, p.last_name, p.admission_no, p.allergies, c.name AS class_name, w.balance, w.daily_limit
             FROM pupils p LEFT JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? AND e.status = 'active'
             LEFT JOIN classes c ON c.id = e.class_id LEFT JOIN canteen_wallets w ON w.pupil_id = p.id
             WHERE p.id = ? AND p.deleted_at IS NULL",
            [current_year()['id'] ?? 0, $id]
        );
    }

    /** Words in the item's allergens that appear in the pupil's allergies (e.g. "groundnut"). */
    public static function allergyClash(?string $pupilAllergies, ?string $itemAllergens): array
    {
        $pa = mb_strtolower((string) $pupilAllergies);
        if (trim($pa) === '' || preg_match('/^(none|nil|no|n\/a|-)\.?$/', trim($pa))) {
            return [];
        }
        $hits = [];
        foreach (preg_split('/[,;\/]+/', mb_strtolower((string) $itemAllergens)) as $a) {
            $a = trim($a);
            $stem = rtrim($a, 's');
            if ($a !== '' && (str_contains($pa, $stem) || ($a === 'groundnut' && str_contains($pa, 'peanut')) || ($a === 'milk' && str_contains($pa, 'dairy')) || ($a === 'wheat' && str_contains($pa, 'gluten')))) {
                $hits[] = $a;
            }
        }
        return array_values(array_unique($hits));
    }

    /** Spent today from the wallet (for the daily limit). */
    public static function spentToday(int $pupilId): float
    {
        return (float) db()->value("SELECT COALESCE(SUM(total), 0) FROM canteen_sales WHERE pupil_id = ? AND paid_by = 'wallet' AND status = 'completed' AND DATE(created_at) = CURDATE()", [$pupilId]);
    }

    /** Add (or take) money on a wallet inside a transaction; returns the new balance. */
    public static function walletMove(Database $d, int $pupilId, string $kind, float $amount, array $extra = []): float
    {
        $d->run('INSERT IGNORE INTO canteen_wallets (pupil_id, balance) VALUES (?, 0)', [$pupilId]);
        $bal = (float) $d->value('SELECT balance FROM canteen_wallets WHERE pupil_id = ? FOR UPDATE', [$pupilId]);
        $new = round($bal + $amount, 2);
        if ($amount < 0 && $new < -0.004) {
            // two sales at the same moment: never let a wallet go below zero
            throw new RuntimeException('wallet_short');
        }
        $d->update('canteen_wallets', ['balance' => $new], 'pupil_id = ?', [$pupilId]);
        $d->insert('canteen_wallet_txns', ['pupil_id' => $pupilId, 'kind' => $kind, 'amount' => round($amount, 2), 'balance_after' => $new,
            'method' => $extra['method'] ?? null, 'reference' => $extra['reference'] ?? null, 'sale_id' => $extra['sale_id'] ?? null, 'created_by' => Auth::id()]);
        return $new;
    }

    public static function menu(string $from, string $to): array
    {
        $out = [];
        foreach (db()->all('SELECT m.*, i.name, i.price, i.allergens FROM canteen_menu m JOIN canteen_items i ON i.id = m.item_id WHERE m.menu_date BETWEEN ? AND ? ORDER BY m.menu_date, FIELD(m.meal,"breakfast","lunch","snack"), i.name', [$from, $to]) as $r) {
            $out[$r['menu_date']][$r['meal']][] = $r;
        }
        return $out;
    }

    public static function planOf(int $pupilId, ?int $termId): ?array
    {
        if (!$termId) {
            return null;
        }
        return db()->one('SELECT m.*, p.name, p.meals, p.price FROM canteen_plan_members m JOIN canteen_plans p ON p.id = m.plan_id WHERE m.pupil_id = ? AND m.term_id = ?', [$pupilId, $termId]);
    }
}
