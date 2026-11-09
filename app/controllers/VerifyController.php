<?php
declare(strict_types=1);

/** Public "is this receipt genuine?" page, opened from the QR code on printed receipts and vouchers. */
class VerifyController extends Controller
{
    public function show(string $token = ''): void
    {
        $code = strtolower(preg_replace('/[^0-9a-fA-F]/', '', (string) ($_GET['code'] ?? '')));
        $d = null;
        $tried = false;
        if ($token !== '') {
            $tried = true;
            $d = Docs::lookup($token);
        } elseif ($code !== '') {
            $tried = true;
            $ip = Security::clientIp();
            $recent = (int) db()->value("SELECT COUNT(*) FROM login_attempts WHERE identifier = 'doc-verify' AND ip = ? AND attempted_at > (UTC_TIMESTAMP() - INTERVAL 10 MINUTE)", [$ip]);
            db()->insert('login_attempts', ['identifier' => 'doc-verify', 'ip' => $ip, 'attempted_at' => gmdate('Y-m-d H:i:s')]);
            if ($recent >= 20) {
                flash('error', 'Too many checks. Try again in a few minutes.');
            } elseif (strlen($code) === 10 && Docs::ready()) {
                $t = db()->value('SELECT token FROM doc_register WHERE token LIKE ? LIMIT 2', [$code . '%']);
                $d = $t ? Docs::lookup((string) $t) : null;
            }
        }
        $this->view('verify/show', ['title' => 'Check a receipt', 'd' => $d, 'tried' => $tried, 'code' => strtoupper($code)], 'layouts/public');
    }
}
