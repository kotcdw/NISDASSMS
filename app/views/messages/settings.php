<?php
$smsLabels = ['log' => 'Test mode', 'hubtel' => 'Hubtel', 'arkesel' => 'Arkesel', 'mnotify' => 'mNotify'];
$waLabels = ['off' => 'Off', 'log' => 'Test mode', 'meta' => 'Meta (WhatsApp Cloud)', 'twilio' => 'Twilio', 'hubtel' => 'Hubtel / other'];
$waModes = ['off' => 'SMS only', 'both' => 'WhatsApp and SMS', 'whatsapp_first' => 'WhatsApp first, then SMS'];
$sp = $s['sms_provider'] ?: 'log';
$wp = setting('whatsapp_provider', 'off');
$saved = fn(string $v) => $v ? '(saved)' : '';
?>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="form-center fs-form">
    <form method="post" action="<?= e(url('messages/settings')) ?>" class="card form"><?= csrf_field() ?>
        <h2>SMS</h2>
        <div class="grid-2">
            <label>Provider<select name="sms_provider">
                <?php foreach (SmsGateway::PROVIDERS as $k => $label): ?><option value="<?= e($k) ?>"<?= selected($k, $sp) ?>><?= e($smsLabels[$k] ?? $label) ?></option><?php endforeach; ?>
            </select></label>
            <label>Sender ID<input name="sms_sender_id" maxlength="11" value="<?= e($s['sms_sender_id']) ?>"></label>
        </div>
        <div data-show-if="sms_provider=arkesel,mnotify"<?= in_array($sp, ['arkesel', 'mnotify'], true) ? '' : ' hidden' ?>>
            <label>API key <small class="muted"><?= $saved((string) $s['sms_api_key']) ?></small><input type="password" name="sms_api_key" autocomplete="off"></label>
        </div>
        <div data-show-if="sms_provider=mnotify"<?= $sp === 'mnotify' ? '' : ' hidden' ?>>
            <label>mNotify endpoint<input name="sms_mnotify_url" value="<?= e($s['sms_mnotify_url']) ?>"></label>
        </div>
        <div data-show-if="sms_provider=hubtel"<?= $sp === 'hubtel' ? '' : ' hidden' ?>>
            <div class="grid-2">
                <label>Client ID<input name="sms_client_id" value="<?= e($s['sms_client_id']) ?>" autocomplete="off"></label>
                <label>Client secret <small class="muted"><?= $saved((string) $s['sms_client_secret']) ?></small><input type="password" name="sms_client_secret" autocomplete="off"></label>
            </div>
        </div>

        <h3>Automatic SMS</h3>
        <label class="fs-check"><input type="checkbox" name="notify_absent" value="1"<?= checked($s['notify_absent'] === '1') ?>> Absent</label>
        <label class="fs-check"><input type="checkbox" name="notify_late" value="1"<?= checked($s['notify_late'] === '1') ?>> Late</label>
        <label class="fs-check"><input type="checkbox" name="notify_admission" value="1"<?= checked($s['notify_admission'] === '1') ?>> Admission progress</label>

        <h3>Sabbath quiet hours</h3>
        <label class="fs-check"><input type="checkbox" name="sabbath_quiet" value="1"<?= checked($s['sabbath_quiet'] === '1') ?>> Hold messages over the Sabbath</label>
        <div class="grid-3">
            <label>Friday from<input type="time" name="sabbath_quiet_start" value="<?= e($s['sabbath_quiet_start']) ?>"></label>
            <label>Saturday until<input type="time" name="sabbath_quiet_end" value="<?= e($s['sabbath_quiet_end']) ?>"></label>
            <label>Cost per SMS (GH₵)<input type="number" step="0.001" min="0" name="sms_cost" value="<?= e($s['sms_cost']) ?>"></label>
        </div>
        <div class="fs-actions"><button class="btn btn-primary" type="submit">Save</button></div>
    </form>

    <form method="post" action="<?= e(url('messages/whatsapp')) ?>" class="card form"><?= csrf_field() ?>
        <h2>WhatsApp</h2>
        <div class="grid-2">
            <label>Provider<select name="whatsapp_provider"><?php foreach (WhatsappGateway::PROVIDERS as $k => $l): ?><option value="<?= $k ?>"<?= selected($wp, $k) ?>><?= e($waLabels[$k] ?? $l) ?></option><?php endforeach; ?></select></label>
            <label>Send by<select name="whatsapp_mode"><?php foreach (WhatsappGateway::MODES as $k => $l): ?><option value="<?= $k ?>"<?= selected(setting('whatsapp_mode', 'off'), $k) ?>><?= e($waModes[$k] ?? $l) ?></option><?php endforeach; ?></select></label>
        </div>
        <div data-show-if="whatsapp_provider=meta"<?= $wp === 'meta' ? '' : ' hidden' ?>>
            <div class="grid-2">
                <label>Phone number ID<input name="wa_meta_phone_id" value="<?= e(setting('wa_meta_phone_id')) ?>"></label>
                <label>Access token <small class="muted"><?= $saved(setting('wa_meta_token')) ?></small><input name="wa_meta_token" type="password"></label>
                <label>Template name<input name="wa_meta_template" value="<?= e(setting('wa_meta_template', 'school_notice')) ?>"></label>
                <label>Template language<input name="wa_meta_language" value="<?= e(setting('wa_meta_language', 'en')) ?>"></label>
                <label>Verify token<input name="wa_meta_verify_token" value="<?= e(setting('wa_meta_verify_token')) ?>"></label>
                <label>App secret <small class="muted"><?= $saved(setting('wa_meta_app_secret')) ?></small><input name="wa_meta_app_secret" type="password"></label>
            </div>
            <label>Webhook URL<input value="<?= e(absolute_url('webhooks/whatsapp')) ?>" readonly></label>
        </div>
        <div data-show-if="whatsapp_provider=twilio"<?= $wp === 'twilio' ? '' : ' hidden' ?>>
            <div class="grid-3">
                <label>Account SID<input name="wa_twilio_sid" value="<?= e(setting('wa_twilio_sid')) ?>"></label>
                <label>Auth token <small class="muted"><?= $saved(setting('wa_twilio_token')) ?></small><input name="wa_twilio_token" type="password"></label>
                <label>Sender number<input name="wa_twilio_from" value="<?= e(setting('wa_twilio_from')) ?>"></label>
            </div>
        </div>
        <div data-show-if="whatsapp_provider=hubtel"<?= $wp === 'hubtel' ? '' : ' hidden' ?>>
            <div class="grid-2">
                <label>Send address<input name="wa_hubtel_url" value="<?= e(setting('wa_hubtel_url')) ?>"></label>
                <label>Sender<input name="wa_hubtel_from" value="<?= e(setting('wa_hubtel_from')) ?>"></label>
                <label>Client ID<input name="wa_hubtel_client_id" value="<?= e(setting('wa_hubtel_client_id')) ?>"></label>
                <label>Client secret <small class="muted"><?= $saved(setting('wa_hubtel_client_secret')) ?></small><input name="wa_hubtel_client_secret" type="password"></label>
            </div>
        </div>
        <div class="fs-actions"><button class="btn btn-primary" type="submit">Save</button></div>
    </form>

    <div class="card fs-tools">
        <form method="post" action="<?= e(url('messages/test')) ?>" class="fs-inline"><?= csrf_field() ?>
            <input name="phone" required placeholder="Phone for a test SMS" aria-label="Phone for a test SMS">
            <button class="btn" type="submit">Send test</button>
        </form>
        <form method="post" action="<?= e(url('messages/balance')) ?>"><?= csrf_field() ?><button class="btn" type="submit">Check account</button></form>
        <span class="fs-status">Scheduled task: <?= $s['cron_last_run'] ? badge(fmt_date($s['cron_last_run'], 'd M H:i'), 'good') : badge('never ran', 'bad') ?></span>
    </div>
</div>
