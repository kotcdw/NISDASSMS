<?php require BASE_PATH . '/app/views/layouts/_public_url.php'; ?>
<?php
$gwLabels = ['none' => 'Off', 'test' => 'Test mode', 'paystack' => 'Paystack', 'hubtel' => 'Hubtel'];
$payer = setting('fee_gateway_payer', 'school') === 'parent' ? 'parent' : 'school';
?>
<div class="page-head">
    <div><h1>Fee settings</h1></div>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<form method="post" action="<?= e(url('fees/settings')) ?>" class="form form-center fs-form">
    <?= csrf_field() ?>
    <div class="card">
        <h2>Online payment</h2>
        <label>Gateway<select name="pay_gateway">
            <?php foreach (PaymentGateway::GATEWAYS as $k => $v): ?><option value="<?= e($k) ?>"<?= selected($gateway, $k) ?>><?= e($gwLabels[$k] ?? $v) ?></option><?php endforeach; ?>
        </select></label>

        <div data-show-if="pay_gateway=paystack,hubtel"<?= in_array($gateway, ['paystack', 'hubtel'], true) ? '' : ' hidden' ?>>
            <label>Web address<input name="public_url" value="<?= e(setting('public_url')) ?>" placeholder="https://sms.nisdas.edu.gh"></label>
        </div>

        <div data-show-if="pay_gateway=paystack"<?= $gateway === 'paystack' ? '' : ' hidden' ?>>
            <label>Secret key <?= setting('paystack_secret') ? '<small class="muted">(saved)</small>' : '' ?><input name="paystack_secret" type="password" autocomplete="off" placeholder="sk_live_…"></label>
            <label>Webhook URL<input value="<?= e(absolute_url('webhooks/paystack')) ?>" readonly></label>
        </div>

        <div data-show-if="pay_gateway=hubtel"<?= $gateway === 'hubtel' ? '' : ' hidden' ?>>
            <div class="grid-2">
                <label>API ID<input name="hubtel_api_id" value="<?= e(setting('hubtel_api_id')) ?>" autocomplete="off"></label>
                <label>API key <?= setting('hubtel_api_key') ? '<small class="muted">(saved)</small>' : '' ?><input name="hubtel_api_key" type="password" autocomplete="off"></label>
            </div>
            <label>Merchant number<input name="hubtel_merchant" value="<?= e(setting('hubtel_merchant')) ?>"></label>
            <label>Callback URL<input value="<?= e(absolute_url('webhooks/hubtel')) ?>" readonly></label>
        </div>

        <div data-show-if="pay_gateway=test,paystack,hubtel"<?= $gateway === 'none' ? ' hidden' : '' ?>>
            <div class="grid-2">
                <label>Charge (%)<input name="fee_gateway_pct" inputmode="decimal" value="<?= e(setting('fee_gateway_pct', '0')) ?>"></label>
                <label>Charge paid by<select name="fee_gateway_payer">
                    <option value="school"<?= selected($payer, 'school') ?>>School</option>
                    <option value="parent"<?= selected($payer, 'parent') ?>>Parent</option>
                </select></label>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Bills &amp; reminders</h2>
        <div class="grid-2">
            <label>Days to pay<input name="fee_due_days" type="number" min="0" max="120" value="<?= (int) setting('fee_due_days', '21') ?>"></label>
            <label>Remind at days late<input name="fee_remind_days" value="<?= e(setting('fee_remind_days', '7,14,30')) ?>" placeholder="7,14,30"></label>
        </div>
        <label class="fs-check"><input type="checkbox" name="fee_stage_reminders" value="1"<?= checked(setting('fee_stage_reminders') === '1') ?>> Late-bill reminders</label>
        <label class="fs-check"><input type="checkbox" name="fee_auto_reminders" value="1"<?= checked(setting('fee_auto_reminders') === '1') ?>> Monday reminder SMS</label>
        <label class="fs-check"><input type="checkbox" name="fee_show_on_report" value="1"<?= checked(setting('fee_show_on_report', '1') === '1') ?>> Arrears on report cards</label>
    </div>

    <div class="card">
        <h2>Printed on bills</h2>
        <label>Mobile Money number<input name="bursar_momo_number" value="<?= e(setting('bursar_momo_number')) ?>" placeholder="024 000 0000"></label>
        <label>Bank account<textarea name="bank_details" rows="2" placeholder="Bank, branch, name, number"><?= e(setting('bank_details')) ?></textarea></label>
    </div>

    <div class="card">
        <h2>Finance dashboard</h2>
        <label>Show transactions from (GH₵)<input name="finance_large_amount" type="number" min="0" step="100" value="<?= e(setting('finance_large_amount', '1000')) ?>"></label>
    </div>

    <div class="fs-actions">
        <button class="btn btn-primary" type="submit">Save</button>
        <?php if (in_array($gateway, ['paystack', 'hubtel'], true)): ?><button class="btn" type="submit" formaction="<?= e(url('fees/settings/test')) ?>" formnovalidate>Test connection</button><?php endif; ?>
    </div>
</form>
