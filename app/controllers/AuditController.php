<?php
declare(strict_types=1);

class AuditController extends Controller
{
    public const PERIODS = ['' => 'Any time', 'today' => 'Today', '7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 3 months'];

    public function index(): void
    {
        $f = [
            'entity' => (string) ($_GET['entity'] ?? ''), 'action' => (string) ($_GET['action'] ?? ''),
            'user' => (int) ($_GET['user'] ?? 0), 'period' => (string) ($_GET['period'] ?? ''), 'q' => trim((string) ($_GET['q'] ?? '')),
        ];
        $sql = 'SELECT a.*, u.name AS user_name FROM audit_log a LEFT JOIN users u ON u.id = a.user_id WHERE 1=1';
        $p = [];
        if ($f['entity'] !== '') {
            $sql .= ' AND a.entity = ?';
            $p[] = $f['entity'];
        }
        if ($f['action'] !== '') {
            $sql .= ' AND a.action = ?';
            $p[] = $f['action'];
        }
        if ($f['user'] > 0) {
            $sql .= ' AND a.user_id = ?';
            $p[] = $f['user'];
        } elseif ($f['user'] === -1) {
            $sql .= ' AND a.user_id IS NULL';
        }
        if ($f['period'] === 'today') {
            $sql .= ' AND a.created_at >= CURDATE()';
        } elseif (isset(self::PERIODS[$f['period']]) && $f['period'] !== '') {
            $sql .= ' AND a.created_at >= CURDATE() - INTERVAL ? DAY';
            $p[] = (int) $f['period'];
        }
        if ($f['q'] !== '') {
            $sql .= ' AND (a.details LIKE ? OR a.action LIKE ? OR u.name LIKE ? OR a.ip LIKE ?)';
            array_push($p, "%{$f['q']}%", "%{$f['q']}%", "%{$f['q']}%", "%{$f['q']}%");
        }
        [$rows, $pager] = paginate($sql . ' ORDER BY a.id DESC', $p, 50);
        $this->view('audit/index', [
            'title' => 'Audit log', 'rows' => $rows, 'pager' => $pager, 'f' => $f, 'entity' => $f['entity'],
            'entities' => db()->all('SELECT DISTINCT entity FROM audit_log WHERE entity IS NOT NULL ORDER BY entity'),
            'actions' => array_column(db()->all('SELECT DISTINCT action FROM audit_log ORDER BY action'), 'action'),
            'people' => db()->all('SELECT DISTINCT u.id, u.name FROM audit_log a JOIN users u ON u.id = a.user_id ORDER BY u.name'),
        ]);
    }

    /** "route: GET users · permission: users.manage" from the stored JSON. */
    public static function readable(?string $json): string
    {
        $d = json_decode((string) $json, true);
        if (!is_array($d)) {
            return (string) $json;
        }
        $out = [];
        foreach ($d as $k => $v) {
            if ($k === 'ip') {
                continue;
            }
            $v = is_scalar($v) || $v === null ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $out[] = str_replace('_', ' ', (string) $k) . ': ' . $v;
        }
        return implode(' · ', $out);
    }
}
