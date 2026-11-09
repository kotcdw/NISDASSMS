<?php
declare(strict_types=1);

/**
 * Online fee payments (Mobile Money / card).
 *  - test     : simulated checkout page inside the app (no real money) — for training
 *  - paystack : Paystack hosted checkout (MTN MoMo, Telecel Cash, AT Money, cards) — secret key
 *  - hubtel   : Hubtel Online Checkout — API ID + API key + merchant account number
 * A payment is ONLY credited after verify() confirms it with the gateway server-to-server.
 */
final class PaymentGateway
{
    public const GATEWAYS = [
        'none'     => 'Off — parents pay at the school or by bank / direct MoMo',
        'test'     => 'Test mode (simulated payment page, no real money)',
        'paystack' => 'Paystack (MoMo & card)',
        'hubtel'   => 'Hubtel Online Checkout (MoMo & card)',
    ];

    public static function active(): string
    {
        $g = setting('pay_gateway', 'none');
        if (is_demo() && $g !== 'none') {
            return 'test'; // the practice copy never moves real money
        }
        return isset(self::GATEWAYS[$g]) ? $g : 'none';
    }

    /** @return array{ok:bool, url:?string, error:?string} */
    public static function initiate(array $op, array $inv, string $returnUrl, string $email, ?string $phone): array
    {
        $gw = $op['gateway'];
        try {
            if ($gw === 'test') {
                return ['ok' => true, 'url' => url('pay/test', ['ref' => $op['reference']]), 'error' => null];
            }
            if ($gw === 'paystack') {
                [$code, $body, $err] = Http::postJson('https://api.paystack.co/transaction/initialize', [
                    'email' => $email, 'amount' => (int) round((float) $op['amount'] * 100), 'currency' => 'GHS',
                    'reference' => $op['reference'], 'callback_url' => $returnUrl, 'channels' => ['mobile_money', 'card'],
                    'metadata' => ['invoice_no' => $inv['invoice_no'], 'pupil_id' => $inv['pupil_id']],
                ], ['Authorization: Bearer ' . setting('paystack_secret')]);
                $j = json_decode($body, true) ?: [];
                if (!$err && $code === 200 && !empty($j['status']) && !empty($j['data']['authorization_url'])) {
                    return ['ok' => true, 'url' => $j['data']['authorization_url'], 'error' => null];
                }
                return ['ok' => false, 'url' => null, 'error' => $err ?: ($j['message'] ?? "HTTP $code")];
            }
            if ($gw === 'hubtel') {
                [$code, $body, $err] = Http::postJson('https://payproxyapi.hubtel.com/items/initiate', [
                    'totalAmount' => round((float) $op['amount'], 2), 'description' => mb_substr($inv['title'] . ' ' . $inv['invoice_no'], 0, 100),
                    'callbackUrl' => self::absolute('webhooks/hubtel'), 'returnUrl' => $returnUrl, 'cancellationUrl' => $returnUrl,
                    'merchantAccountNumber' => setting('hubtel_merchant'), 'clientReference' => $op['reference'],
                    'payeeMobileNumber' => $phone,
                ], [], setting('hubtel_api_id') . ':' . setting('hubtel_api_key'));
                $j = json_decode($body, true) ?: [];
                $url = $j['data']['checkoutUrl'] ?? null;
                if (!$err && $code >= 200 && $code < 300 && $url) {
                    return ['ok' => true, 'url' => $url, 'error' => null];
                }
                return ['ok' => false, 'url' => null, 'error' => $err ?: ($j['message'] ?? "HTTP $code")];
            }
        } catch (Throwable $e) {
            return ['ok' => false, 'url' => null, 'error' => $e->getMessage()];
        }
        return ['ok' => false, 'url' => null, 'error' => 'Online payment is not enabled.'];
    }

    /**
     * Ask the gateway whether a payment really succeeded.
     * @return array{status:string, amount:?float, message:string}  status = success | failed | pending
     */
    public static function verify(array $op): array
    {
        $gw = $op['gateway'];
        try {
            if ($gw === 'test') {
                // The simulated checkout stores the outcome in gateway_message
                $s = $op['gateway_message'] === 'TEST-APPROVED' ? 'success' : ($op['gateway_message'] === 'TEST-DECLINED' ? 'failed' : 'pending');
                return ['status' => $s, 'amount' => (float) $op['amount'], 'message' => 'Test payment'];
            }
            if ($gw === 'paystack') {
                [$code, $body, $err] = self::get('https://api.paystack.co/transaction/verify/' . rawurlencode($op['reference']), ['Authorization: Bearer ' . setting('paystack_secret')]);
                $j = json_decode($body, true) ?: [];
                $d = $j['data'] ?? [];
                if ($err || $code !== 200 || empty($j['status'])) {
                    return ['status' => 'pending', 'amount' => null, 'message' => $err ?: ($j['message'] ?? "HTTP $code")];
                }
                if (($d['status'] ?? '') === 'success' && ($d['currency'] ?? 'GHS') === 'GHS') {
                    return ['status' => 'success', 'amount' => ((int) $d['amount']) / 100, 'message' => (string) ($d['gateway_response'] ?? 'Approved')];
                }
                return ['status' => in_array($d['status'] ?? '', ['failed', 'abandoned', 'reversed'], true) ? 'failed' : 'pending', 'amount' => null, 'message' => (string) ($d['gateway_response'] ?? $d['status'] ?? '')];
            }
            if ($gw === 'hubtel') {
                $url = 'https://api-txnstatus.hubtel.com/transactions/' . rawurlencode(setting('hubtel_merchant')) . '/status?clientReference=' . rawurlencode($op['reference']);
                [$code, $body, $err] = self::get($url, [], setting('hubtel_api_id') . ':' . setting('hubtel_api_key'));
                $j = json_decode($body, true) ?: [];
                $d = $j['data'] ?? [];
                $status = strtolower((string) ($d['status'] ?? ''));
                if (!$err && $code === 200 && in_array($status, ['paid', 'success', 'successful'], true)) {
                    return ['status' => 'success', 'amount' => isset($d['amount']) ? (float) $d['amount'] : null, 'message' => 'Paid'];
                }
                if (in_array($status, ['unpaid', 'failed', 'cancelled'], true)) {
                    return ['status' => 'failed', 'amount' => null, 'message' => $status];
                }
                return ['status' => 'pending', 'amount' => null, 'message' => $err ?: ($j['message'] ?? "HTTP $code")];
            }
        } catch (Throwable $e) {
            return ['status' => 'pending', 'amount' => null, 'message' => $e->getMessage()];
        }
        return ['status' => 'pending', 'amount' => null, 'message' => 'Unknown gateway'];
    }

    /**
     * Verify an online payment and credit it once. Safe to call many times (return page, webhook, staff button).
     * @return string success | failed | pending
     */
    /** Charge added to a parent's online payment (setting "fee_gateway_payer" = parent), else 0. */
    public static function parentCharge(float $amount): float
    {
        $pct = (float) setting('fee_gateway_pct', '0');
        return $pct > 0 && setting('fee_gateway_payer') === 'parent' ? round($amount * $pct / 100, 2) : 0.0;
    }

    /**
     * Cash book entries for the gateway's fee on a successful online payment: the gateway keeps its % (bank charge),
     * and when the parent paid the charge on top, that part is income. Inside the settle transaction.
     */
    private static function postCharges(Database $d, array $op, float $paid, float $charge): void
    {
        $pct = (float) setting('fee_gateway_pct', '0');
        $acct = Finance::accountForMethod('online');
        if (!$acct) {
            return;
        }
        if ($charge > 0) {
            Finance::post($d, [
                'kind' => 'income', 'txn_date' => date('Y-m-d'), 'account_id' => $acct, 'category_id' => $d->value("SELECT id FROM fin_categories WHERE code = 'OCHG'") ?: null,
                'amount' => $charge, 'description' => 'Online payment charge paid by the parent (' . $op['reference'] . ')', 'source' => 'gateway_charge', 'source_id' => $op['id'],
            ]);
        }
        $fee = $pct > 0 ? round($paid * $pct / 100, 2) : 0.0;
        if ($fee > 0) {
            Finance::post($d, [
                'kind' => 'expense', 'txn_date' => date('Y-m-d'), 'account_id' => $acct, 'category_id' => $d->value("SELECT id FROM fin_categories WHERE code = 'BANK'") ?: null,
                'amount' => $fee, 'description' => 'Gateway fee on online payment ' . $op['reference'] . ' (' . rtrim(rtrim(number_format($pct, 2), '0'), '.') . '%)', 'source' => 'gateway_fee', 'source_id' => $op['id'],
            ]);
        }
    }

    public static function settle(string $reference): string
    {
        $op = db()->one('SELECT * FROM online_payments WHERE reference = ?', [$reference]);
        if (!$op) {
            return 'failed';
        }
        if ($op['status'] === 'success') {
            return 'success';
        }
        if (strpos((string) $op['gateway_message'], 'PAID but bill cancelled') === 0) {
            return 'pending'; // already flagged to the Bursar
        }
        $v = self::verify($op);
        if ($v['status'] === 'success') {
            // Never credit more than the gateway confirmed
            $amount = $v['amount'] !== null ? min((float) $v['amount'], (float) $op['amount']) : (float) $op['amount'];
            if ($v['amount'] !== null && $v['amount'] + 0.01 < (float) $op['amount']) {
                db()->update('online_payments', ['status' => 'failed', 'gateway_message' => 'Amount mismatch: paid ' . $v['amount']], "id = ? AND status = 'initiated'", [$op['id']]);
                return 'failed';
            }
            try {
                // Lock the attempt so a webhook and the return page arriving together credit it only once
                [$isNew, $pid] = db()->transaction(function (Database $d) use ($op, $amount, $reference, $v) {
                    $row = $d->one('SELECT status, payment_id FROM online_payments WHERE id = ? FOR UPDATE', [$op['id']]);
                    if ($row['status'] === 'success') {
                        return [false, (int) $row['payment_id']];
                    }
                    $charge = round((float) ($op['charge'] ?? 0), 2);
                    $pid = Fees::recordPayment((int) $op['invoice_id'], round($amount - $charge, 2), 'online', [
                        'reference' => $reference, 'payer_phone' => $op['payer_phone'], 'gateway' => $op['gateway'], 'gateway_ref' => $op['gateway'] . ':' . $reference,
                    ]);
                    $d->update('online_payments', ['status' => 'success', 'payment_id' => $pid, 'gateway_message' => mb_substr($v['message'], 0, 255)], 'id = ?', [$op['id']]);
                    self::postCharges($d, $op, $amount, $charge);
                    return [true, $pid];
                });
            } catch (RuntimeException $e) {
                // Bill was cancelled while the parent was paying: keep the record so the Bursar can refund or re-allocate
                db()->update('online_payments', ['gateway_message' => 'PAID but bill cancelled - Bursar to re-allocate or refund'], 'id = ?', [$op['id']]);
                Notifier::inappPermission('fees.manage', 'Online payment needs attention', 'Reference ' . $reference . ' was paid but the bill had been cancelled.', 'fees/online');
                return 'pending';
            }
            if (!$isNew) {
                return 'success';
            }
            Fees::smsReceipt($pid);
            $inv = db()->one('SELECT i.invoice_no, p.first_name, p.last_name FROM invoices i JOIN pupils p ON p.id = i.pupil_id WHERE i.id = ?', [$op['invoice_id']]);
            Notifier::inappPermission('fees.view', 'Online payment received', Fees::money($amount) . ' for ' . $inv['first_name'] . ' ' . $inv['last_name'] . ' (' . $inv['invoice_no'] . ')', 'fees/invoices/' . $op['invoice_id']);
            // Paid twice (e.g. cash at the office while the parent was paying online): the money is in, so flag the credit
            $over = (float) db()->value('SELECT paid - total FROM invoices WHERE id = ?', [$op['invoice_id']]);
            if ($over > 0.004 && Family::ready()) { // keep the extra as family credit for the next bill
                $over = db()->transaction(fn(Database $d) => Family::moveOverpayment($d, (int) $op['invoice_id'], 'overpayment', 'Paid twice online (' . $reference . ')'));
                Notifier::inappPermission('fees.manage', 'Bill overpaid by ' . Fees::money($over), $inv['invoice_no'] . ' (' . $inv['first_name'] . ' ' . $inv['last_name'] . ') was paid twice. The extra is kept as family credit for the next bill (or refund it from the family account).', 'fees/invoices/' . $op['invoice_id']);
                audit('invoice_overpaid', 'invoices', (int) $op['invoice_id'], ['over' => round($over, 2), 'reference' => $reference]);
            } elseif ($over > 0.004) {
                Notifier::inappPermission('fees.manage', 'Bill overpaid by ' . Fees::money($over), $inv['invoice_no'] . ' (' . $inv['first_name'] . ' ' . $inv['last_name'] . ') was paid twice. Refund the parent or carry the credit to the next bill.', 'fees/invoices/' . $op['invoice_id']);
                audit('invoice_overpaid', 'invoices', (int) $op['invoice_id'], ['over' => round($over, 2), 'reference' => $reference]);
            }
            Notifier::process(10);
            audit('online_payment', 'payments', $pid, ['reference' => $reference, 'gateway' => $op['gateway']]);
            return 'success';
        }
        if ($v['status'] === 'failed') {
            db()->update('online_payments', ['status' => 'failed', 'gateway_message' => mb_substr($v['message'], 0, 255)], "id = ? AND status = 'initiated'", [$op['id']]);
        }
        return $v['status'];
    }

    public static function absolute(string $route, array $query = []): string
    {
        return absolute_url($route, $query);
    }

    /**
     * Check that the saved keys are accepted by the gateway (no money moves).
     * @return array{ok:bool, message:string}
     */
    public static function testConnection(): array
    {
        $gw = self::active();
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'message' => 'The PHP curl extension is off — turn on extension=curl in php.ini.'];
        }
        try {
            if ($gw === 'paystack') {
                if (setting('paystack_secret') === '') {
                    return ['ok' => false, 'message' => 'No Paystack secret key saved yet.'];
                }
                [$code, $body, $err] = self::get('https://api.paystack.co/balance', ['Authorization: Bearer ' . setting('paystack_secret')]);
                $j = json_decode($body, true) ?: [];
                if (!$err && $code === 200 && !empty($j['status'])) {
                    $live = str_starts_with(setting('paystack_secret'), 'sk_live_');
                    return ['ok' => true, 'message' => 'Paystack accepted the key (' . ($live ? 'LIVE — real money' : 'TEST key — no real money') . ').'];
                }
                return ['ok' => false, 'message' => 'Paystack refused the key: ' . ($err ?: ('HTTP ' . $code . ' ' . ($j['message'] ?? '')))];
            }
            if ($gw === 'hubtel') {
                if (setting('hubtel_api_id') === '' || setting('hubtel_api_key') === '' || setting('hubtel_merchant') === '') {
                    return ['ok' => false, 'message' => 'Enter the Hubtel API ID, API key and merchant account number.'];
                }
                [$code, $body, $err] = self::get('https://api-txnstatus.hubtel.com/transactions/' . rawurlencode(setting('hubtel_merchant')) . '/status?clientReference=connection-test', [], setting('hubtel_api_id') . ':' . setting('hubtel_api_key'));
                if ($err) {
                    return ['ok' => false, 'message' => 'Could not reach Hubtel: ' . $err];
                }
                if ($code === 401 || $code === 403) {
                    return ['ok' => false, 'message' => 'Hubtel refused the API ID / key (HTTP ' . $code . '). Hubtel may also need this server\'s IP address whitelisted.'];
                }
                return ['ok' => true, 'message' => 'Hubtel accepted the credentials (HTTP ' . $code . ' for a test lookup).'];
            }
            return ['ok' => $gw !== 'none', 'message' => $gw === 'test' ? 'Test mode needs no keys.' : 'Online payment is off.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /** @return array{0:int,1:string,2:?string} */
    private static function get(string $url, array $headers = [], ?string $basic = null): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers)]);
        if ($basic !== null) {
            curl_setopt($ch, CURLOPT_USERPWD, $basic);
        }
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = $resp === false ? curl_error($ch) : null;
        curl_close($ch);
        return [$code, $resp === false ? '' : (string) $resp, $err];
    }
}
