<?php
declare(strict_types=1);

/** Requisition workflow rules. */
final class Requisition
{
    public const STATUS = [
        'draft'       => ['Draft', 'neutral'],
        'submitted'   => ['Awaiting Head approval', 'warn'],
        'approved_l1' => ['Awaiting Manager approval', 'warn'],
        'approved'    => ['Approved — to be paid', 'info'],
        'returned'    => ['Returned for changes', 'warn'],
        'rejected'    => ['Rejected', 'bad'],
        'paid'        => ['Paid — awaiting receipts', 'info'],
        'retired'     => ['Completed', 'good'],
        'cancelled'   => ['Cancelled', 'neutral'],
    ];

    public const DEPARTMENTS = ['Creche & KG', 'Lower Primary (B1–B3)', 'Upper Primary (B4–B6)', 'JHS', 'Administration', 'Kitchen / Feeding', 'Maintenance', 'Sports & Clubs', 'Chaplaincy / Worship', 'ICT'];

    public static function badge(string $status): string
    {
        [$l, $t] = self::STATUS[$status] ?? [$status, 'neutral'];
        return badge($l, $t);
    }

    public static function editable(array $r): bool
    {
        return in_array($r['status'], ['draft', 'returned'], true) && (int) $r['requested_by'] === Auth::id();
    }

    public static function canSee(array $r): bool
    {
        return (int) $r['requested_by'] === Auth::id() || can('requisitions.view_all') || can('requisitions.approve') || can('requisitions.pay');
    }

    public static function threshold(): float
    {
        return (float) setting('req_second_threshold', '5000');
    }

    public static function log(Database $d, int $id, string $action, ?string $from, ?string $to, ?string $note = null): void
    {
        $d->insert('requisition_log', [
            'requisition_id' => $id, 'action' => $action, 'from_status' => $from, 'to_status' => $to,
            'note' => $note !== null ? mb_substr($note, 0, 500) : null, 'user_id' => Auth::id(),
        ]);
    }

    public static function recalc(Database $d, int $id): float
    {
        $amt = round((float) $d->value('SELECT COALESCE(SUM(quantity * unit_cost), 0) FROM requisition_items WHERE requisition_id = ?', [$id]), 2);
        $d->update('requisitions', ['amount' => $amt], 'id = ?', [$id]);
        return $amt;
    }

    /** In-app message to the person who made the request. */
    public static function tellRequester(array $r, string $title, ?string $body = null): void
    {
        Notifier::inapp([(int) $r['requested_by']], $title, $body, 'requisitions/' . $r['id']);
    }
}
