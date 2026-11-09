<?php
declare(strict_types=1);

/** CRM: admission enquiries from first contact until the child applies and is enrolled, and marketing audiences. */
final class Crm
{
    public const STAGES = [
        'new'          => ['New enquiry', 'info'],
        'contacted'    => ['Contacted', 'neutral'],
        'visit_booked' => ['School visit booked', 'warn'],
        'visited'      => ['Visited the school', 'warn'],
        'applied'      => ['Applied', 'good'],
        'enrolled'     => ['Enrolled', 'good'],
        'lost'         => ['Not joining', 'bad'],
    ];
    public const OPEN = ['new', 'contacted', 'visit_booked', 'visited'];
    public const SOURCES = ['phone' => 'Phone call', 'walk_in' => 'Walk-in', 'website' => 'Website form', 'referral' => 'Referred by a parent', 'social' => 'Facebook / WhatsApp',
        'church' => 'Church announcement', 'event' => 'Open day / event', 'other' => 'Other'];
    public const ACTIVITIES = ['call' => 'Phone call', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'visit' => 'School visit', 'email' => 'Email', 'note' => 'Note'];
    public const AUDIENCES = [
        'enquiries'       => 'Open enquiries (not yet applied)',
        'enquiries_stage' => 'Enquiries at one stage',
        'enquiries_lost'  => 'Enquiries marked "not joining"',
        'past_pupils'     => 'Parents of past students (left, transferred, graduated)',
        'current_parents' => 'Parents of current students',
        'custom'          => 'Phone numbers I type',
    ];
    public const MAX_RECIPIENTS = 2000;

    public static function addActivity(int $enquiryId, string $kind, ?string $note): void
    {
        db()->insert('crm_activities', ['enquiry_id' => $enquiryId, 'kind' => $kind, 'note' => $note !== null ? mb_substr($note, 0, 1000) : null, 'created_by' => Auth::id()]);
        db()->update('crm_enquiries', ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$enquiryId]);
    }

    /** Link enquiries to online / office applications made with the same phone number (moves them to Applied / Enrolled). */
    public static function syncApplications(): int
    {
        try {
            $rows = db()->all("SELECT id, phone, created_at, stage FROM crm_enquiries WHERE stage IN ('new','contacted','visit_booked','visited','applied')");
        } catch (PDOException $e) {
            return 0;
        }
        $n = 0;
        foreach ($rows as $e) {
            $app = db()->one(
                "SELECT id, stage FROM applications WHERE (guardian_phone = ? OR guardian_phone_alt = ?) AND created_at >= ? - INTERVAL 1 DAY AND stage NOT IN ('rejected','withdrawn') ORDER BY id DESC LIMIT 1",
                [$e['phone'], $e['phone'], $e['created_at']]
            );
            if (!$app) {
                continue;
            }
            $to = $app['stage'] === 'enrolled' ? 'enrolled' : 'applied';
            if ($to === $e['stage']) {
                continue;
            }
            db()->update('crm_enquiries', ['stage' => $to, 'application_id' => $app['id'], 'next_follow_up' => null, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$e['id']]);
            db()->insert('crm_activities', ['enquiry_id' => $e['id'], 'kind' => 'stage', 'note' => $to === 'enrolled' ? 'Enrolled (found automatically from the admission record).' : 'Applied (application found with the same phone number).']);
            $n++;
        }
        return $n;
    }

    /** Phone numbers for a marketing audience: phone => [name, enquiry_id]. Opted-out numbers are left out. */
    public static function audience(string $aud, string $value = '', string $custom = ''): array
    {
        $out = [];
        $add = function (?string $phone, ?string $name, ?int $enq = null) use (&$out) {
            $p = Admission::normalisePhone((string) $phone);
            if ($p && SmsGateway::validPhone($p) && !isset($out[$p])) {
                $out[$p] = [$name, $enq];
            }
        };
        $yid = current_year()['id'] ?? 0;
        if ($aud === 'enquiries' || $aud === 'enquiries_stage' || $aud === 'enquiries_lost') {
            $stages = $aud === 'enquiries' ? self::OPEN : ($aud === 'enquiries_lost' ? ['lost'] : (isset(self::STAGES[$value]) ? [$value] : []));
            if ($stages) {
                $in = implode(',', array_fill(0, count($stages), '?'));
                foreach (db()->all("SELECT id, phone, parent_name FROM crm_enquiries WHERE opted_out = 0 AND stage IN ($in) ORDER BY id", $stages) as $r) {
                    $add($r['phone'], $r['parent_name'], (int) $r['id']);
                }
            }
        } elseif ($aud === 'past_pupils') {
            foreach (db()->all("SELECT DISTINCT g.phone, g.name FROM pupils p JOIN pupil_guardian pg ON pg.pupil_id = p.id AND pg.is_primary = 1 JOIN guardians g ON g.id = pg.guardian_id
                                WHERE p.deleted_at IS NULL AND p.status IN ('withdrawn','transferred','graduated')") as $r) {
                $add($r['phone'], $r['name']);
            }
        } elseif ($aud === 'current_parents') {
            foreach (Audience::phones(in_array($value, ['creche', 'kg', 'primary', 'jhs'], true) ? 'parents:' . $value : 'parents:all') as $phone => $name) {
                $add($phone, $name);
            }
        } elseif ($aud === 'custom') {
            // One number per line or separated by commas; spaces inside a number (024 412 3456) are allowed
            foreach (preg_split('/[,;\r\n]+/', $custom) as $p) {
                $add($p, null);
            }
        }
        if ($out) {
            $opt = array_flip(array_column(db()->all('SELECT phone FROM mkt_optouts'), 'phone'));
            $out = array_diff_key($out, $opt);
        }
        return $out;
    }

    /** Delivery and results for a sent campaign. */
    public static function results(int $campaignId, ?string $sentAt): array
    {
        $r = db()->one(
            "SELECT COUNT(*) AS total,
                    SUM(o.status = 'sent') AS sent, SUM(o.status = 'failed') AS failed, SUM(o.status = 'queued') AS queued, SUM(o.read_at IS NOT NULL) AS seen
             FROM mkt_recipients m LEFT JOIN notification_outbox o ON o.id = m.outbox_id WHERE m.campaign_id = ?",
            [$campaignId]
        );
        $conv = $sentAt ? (int) db()->value(
            "SELECT COUNT(*) FROM mkt_recipients m JOIN crm_enquiries e ON e.id = m.enquiry_id WHERE m.campaign_id = ? AND e.stage IN ('visit_booked','visited','applied','enrolled') AND e.updated_at >= ?",
            [$campaignId, $sentAt]
        ) : 0;
        $apps = $sentAt ? (int) db()->value(
            "SELECT COUNT(DISTINCT a.id) FROM mkt_recipients m JOIN applications a ON (a.guardian_phone = m.phone OR a.guardian_phone_alt = m.phone) WHERE m.campaign_id = ? AND a.created_at >= ?",
            [$campaignId, $sentAt]
        ) : 0;
        return ['total' => (int) $r['total'], 'sent' => (int) $r['sent'], 'failed' => (int) $r['failed'], 'queued' => (int) $r['queued'], 'seen' => (int) $r['seen'], 'moved' => $conv, 'applications' => $apps];
    }
}
