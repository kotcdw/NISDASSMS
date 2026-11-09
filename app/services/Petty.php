<?php
declare(strict_types=1);

/**
 * Petty cash (imprest): a cash box of kind 'petty' held by a custodian with a fixed float. Small spends are recorded
 * as petty cash vouchers (PCV) from the box; the Bursar tops the box back up to its float, which covers exactly the
 * vouchers spent since the last top-up.
 */
final class Petty
{
    public static function ready(): bool
    {
        static $ok = null;
        if ($ok === null) {
            $ok = (bool) db()->value("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'fin_accounts' AND column_name = 'custodian_id'");
        }
        return $ok;
    }

    public static function boxes(bool $all = true): array
    {
        $where = "a.kind = 'petty'" . ($all ? '' : ' AND a.custodian_id = ' . (int) Auth::id() . ' AND a.is_active = 1');
        $rows = db()->all("SELECT a.*, u.name AS custodian_name FROM fin_accounts a LEFT JOIN users u ON u.id = a.custodian_id WHERE $where ORDER BY a.is_active DESC, a.sort_order, a.name");
        foreach ($rows as &$r) {
            $r['balance'] = Finance::balance((int) $r['id']);
            $r['unclaimed'] = self::unclaimed((int) $r['id']);
        }
        unset($r);
        return $rows;
    }

    public static function mine(): bool
    {
        return self::ready() && (bool) db()->value("SELECT 1 FROM fin_accounts WHERE kind = 'petty' AND is_active = 1 AND custodian_id = ?", [Auth::id()]);
    }

    public static function box(int $id): ?array
    {
        return db()->one("SELECT a.*, u.name AS custodian_name FROM fin_accounts a LEFT JOIN users u ON u.id = a.custodian_id WHERE a.id = ? AND a.kind = 'petty'", [$id]);
    }

    public static function canUse(array $box): bool
    {
        return can('finance.manage') || ((int) $box['custodian_id'] === (int) Auth::id() && (int) $box['is_active'] === 1);
    }

    public static function canSee(array $box): bool
    {
        return can('finance.view') || (int) $box['custodian_id'] === (int) Auth::id();
    }

    /** Vouchers spent since the last top-up (to be claimed back). */
    public static function unclaimedRows(int $boxId): array
    {
        return db()->all(
            "SELECT t.*, c.name AS category_name, u.name AS by_name FROM fin_transactions t LEFT JOIN fin_categories c ON c.id = t.category_id LEFT JOIN users u ON u.id = t.created_by
             WHERE t.account_id = ? AND t.kind = 'expense' AND t.status = 'posted' AND t.petty_topup_id IS NULL ORDER BY t.txn_date, t.id",
            [$boxId]
        );
    }

    public static function unclaimed(int $boxId): float
    {
        return round((float) db()->value("SELECT COALESCE(SUM(amount), 0) FROM fin_transactions WHERE account_id = ? AND kind = 'expense' AND status = 'posted' AND petty_topup_id IS NULL", [$boxId]), 2);
    }
}
