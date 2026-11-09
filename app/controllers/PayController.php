<?php
declare(strict_types=1);

/**
 * Parent-facing bill page (link sent by SMS) and payment gateway callbacks.
 * No login: the 24-character random key in the link identifies the bill.
 */
class PayController extends Controller
{
    public function show(string $token): void
    {
        $inv = $this->invoice($token);
        // The pay form redirects to the gateway's checkout site, so allow HTTPS form targets on this page only
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; frame-ancestors 'self'; form-action 'self' https:");
        header('X-Robots-Tag: noindex');
        $this->view('pay/show', [
            'title' => 'School fees — ' . $inv['invoice_no'], 'inv' => $inv,
            'lines' => db()->all('SELECT description, amount, discount, discount_note FROM invoice_lines WHERE invoice_id = ? ORDER BY id', [$inv['id']]),
            'payments' => db()->all("SELECT receipt_no, amount, method, paid_on FROM payments WHERE invoice_id = ? AND status = 'completed' ORDER BY id", [$inv['id']]),
            'gateway' => PaymentGateway::active(), 'balance' => Fees::balance($inv),
        ], 'layouts/public');
    }

    public function start(string $token): void
    {
        $inv = $this->invoice($token);
        $gw = PaymentGateway::active();
        $bal = Fees::balance($inv);
        $amount = round((float) str_replace(',', '', (string) ($_POST['amount'] ?? $bal)), 2);
        $phone = Admission::normalisePhone((string) ($_POST['phone'] ?? ''));
        $back = 'pay/' . $token;
        if ($gw === 'none' || $inv['status'] === 'cancelled' || $bal <= 0) {
            flash('error', 'Online payment is not available for this bill.');
            redirect($back);
        }
        if ($amount < 1 || $amount > $bal + 0.004) {
            flash('error', 'Enter an amount between GH₵ 1.00 and ' . Fees::money($bal) . '.');
            redirect($back);
        }
        if (!$phone || !preg_match('/^0[2-5]\d{8}$/', $phone)) {
            flash('error', 'Enter the Mobile Money number you will pay from (e.g. 024 123 4567).');
            redirect($back);
        }
        // Basic abuse limit: at most 10 attempts per bill per hour
        if ((int) db()->value('SELECT COUNT(*) FROM online_payments WHERE invoice_id = ? AND created_at > (NOW() - INTERVAL 1 HOUR)', [$inv['id']]) >= 10) {
            flash('error', 'Too many attempts. Please wait a while and try again, or pay at the school.');
            redirect($back);
        }
        $ref = 'NIS' . strtoupper(bin2hex(random_bytes(8))); // 19 chars: fits Hubtel's 32-char limit
        // Online payment charge added to what the parent pays (setting); the bill is credited with $amount only
        $charge = PaymentGateway::parentCharge($amount);
        $opId = db()->insert('online_payments', [
            'reference' => $ref, 'invoice_id' => $inv['id'], 'amount' => round($amount + $charge, 2), 'gateway' => $gw,
            'payer_phone' => $phone, 'ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        ] + ($charge > 0 ? ['charge' => $charge] : []));
        $op = db()->one('SELECT * FROM online_payments WHERE id = ?', [$opId]);
        $g = Fees::guardian((int) $inv['pupil_id']);
        $email = ($g && filter_var((string) $g['email'], FILTER_VALIDATE_EMAIL)) ? $g['email'] : (filter_var((string) setting('school_email'), FILTER_VALIDATE_EMAIL) ? setting('school_email') : 'fees@nisdas.local');
        // Back to the address the parent is using (works on the school PC and online alike)
        $return = request_url('pay/' . $token . '/return', ['ref' => $ref]);
        $r = PaymentGateway::initiate($op, $inv, $return, $email, $phone);
        if (!$r['ok']) {
            db()->update('online_payments', ['status' => 'failed', 'gateway_message' => mb_substr((string) $r['error'], 0, 255)], 'id = ?', [$opId]);
            flash('error', 'We could not start the payment right now. Please try again later or pay at the school.');
            redirect($back);
        }
        db()->update('online_payments', ['checkout_url' => mb_substr((string) $r['url'], 0, 500)], 'id = ?', [$opId]);
        header('Location: ' . $r['url']);
        exit;
    }

    /** Parent comes back from the gateway: confirm with the gateway before showing "paid". */
    public function returned(string $token): void
    {
        $inv = $this->invoice($token);
        $ref = (string) ($_GET['ref'] ?? $_GET['reference'] ?? '');
        $op = $ref !== '' ? db()->one('SELECT * FROM online_payments WHERE reference = ? AND invoice_id = ?', [$ref, $inv['id']]) : null;
        if (!$op) {
            redirect('pay/' . $token);
        }
        $result = PaymentGateway::settle($op['reference']);
        $receipt = $result === 'success' ? db()->one('SELECT p.receipt_no, p.amount FROM online_payments op JOIN payments p ON p.id = op.payment_id WHERE op.id = ?', [$op['id']]) : null;
        $this->view('pay/result', [
            'title' => 'Payment ' . $result, 'inv' => $this->invoice($token), 'result' => $result, 'receipt' => $receipt, 'op' => $op,
        ], 'layouts/public');
    }

    /** Simulated checkout used only in Test mode. */
    public function testPage(): void
    {
        $op = $this->testOp();
        $inv = db()->one('SELECT * FROM invoices WHERE id = ?', [$op['invoice_id']]);
        $this->view('pay/test', ['title' => 'Test checkout', 'op' => $op, 'inv' => $inv], 'layouts/public');
    }

    public function testDecide(): void
    {
        $op = $this->testOp();
        $ok = ($_POST['decision'] ?? '') === 'approve';
        db()->update('online_payments', ['gateway_message' => $ok ? 'TEST-APPROVED' : 'TEST-DECLINED'], "id = ? AND status = 'initiated'", [$op['id']]);
        $key = db()->value('SELECT public_key FROM invoices WHERE id = ?', [$op['invoice_id']]);
        redirect('pay/' . $key . '/return', ['ref' => $op['reference']]);
    }

    /** Paystack webhook: signed with HMAC-SHA512 of the raw body using the secret key. */
    public function paystackHook(): void
    {
        $raw = (string) file_get_contents('php://input');
        $secret = (string) setting('paystack_secret');
        $sig = (string) ($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '');
        if ($secret === '' || $sig === '' || !hash_equals(hash_hmac('sha512', $raw, $secret), $sig)) {
            http_response_code(401);
            echo 'invalid signature';
            return;
        }
        $j = json_decode($raw, true) ?: [];
        $ref = (string) ($j['data']['reference'] ?? '');
        if (($j['event'] ?? '') === 'charge.success' && $ref !== '' && db()->value("SELECT id FROM online_payments WHERE reference = ? AND gateway = 'paystack'", [$ref])) {
            PaymentGateway::settle($ref); // re-verifies with Paystack before crediting
        }
        http_response_code(200);
        echo 'ok';
    }

    /**
     * Hubtel callback. Hubtel does not sign callbacks, so we never trust the body:
     * we only take the reference from it and ask Hubtel's status API whether it was paid.
     */
    public function hubtelHook(): void
    {
        $raw = (string) file_get_contents('php://input');
        $j = json_decode($raw, true) ?: [];
        $ref = (string) ($j['Data']['ClientReference'] ?? $j['data']['clientReference'] ?? $j['ClientReference'] ?? '');
        if ($ref !== '' && preg_match('/^NIS[0-9A-F]{16}$/', $ref) && db()->value("SELECT id FROM online_payments WHERE reference = ? AND gateway = 'hubtel'", [$ref])) {
            PaymentGateway::settle($ref);
        }
        http_response_code(200);
        echo 'ok';
    }

    private function testOp(): array
    {
        $ref = (string) ($_GET['ref'] ?? $_POST['ref'] ?? '');
        $op = $ref !== '' ? db()->one("SELECT * FROM online_payments WHERE reference = ? AND gateway = 'test' AND status = 'initiated'", [$ref]) : null;
        if (!$op || PaymentGateway::active() !== 'test') {
            $this->notFound();
        }
        return $op;
    }

    private function invoice(string $token): array
    {
        $inv = db()->one(
            'SELECT i.*, p.first_name, p.middle_name, p.last_name, p.admission_no, c.name AS class_name, t.name AS term_name, y.name AS year_name
             FROM invoices i JOIN pupils p ON p.id = i.pupil_id LEFT JOIN classes c ON c.id = i.class_id
             LEFT JOIN terms t ON t.id = i.term_id LEFT JOIN academic_years y ON y.id = t.academic_year_id
             WHERE i.public_key = ?',
            [$token]
        );
        if (!$inv) {
            $this->notFound();
        }
        return $inv;
    }
}
