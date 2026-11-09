<div class="page-head"><div><h1>Set up 2-step sign-in</h1></div></div>
<?php if ($required): ?><div class="alert alert-warning">Your role requires 2-step sign-in. Finish these steps to continue.</div><?php endif; ?>
<div class="cols">
    <div class="card">
        <h2>1. Install an authenticator app</h2>
        <p>On your phone, install <strong>Google Authenticator</strong> or <strong>Microsoft Authenticator</strong> (free, from Play Store or App Store). It works without data or credit.</p>
        <h2>2. Scan this code</h2>
        <p>In the app tap <strong>+</strong> → <strong>Scan a QR code</strong>.</p>
        <div data-qr="<?= e($uri) ?>" style="width:220px;height:220px;background:#fff;padding:4px;border:1px solid var(--border, #ddd)"><small class="muted">Loading QR code…</small></div>
        <details style="margin-top:.8rem"><summary class="hint" style="cursor:pointer">Cannot scan? Type this key instead</summary>
            <p style="font-family:ui-monospace,monospace;font-size:1.05rem;letter-spacing:.08em;word-break:break-all"><?= e(trim(chunk_split($secret, 4, ' '))) ?></p>
            <p class="hint">Choose "Enter a setup key", account name: your email, key type: time-based.</p>
        </details>
    </div>
    <div class="card">
        <h2>3. Type the code the app shows</h2>
        <form method="post" action="<?= e(url('account/2fa')) ?>" class="form">
            <?= csrf_field() ?>
            <label>6-digit code <input name="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="7" pattern="[0-9 ]{6,7}" style="font-size:1.4rem;letter-spacing:.3em;text-align:center;max-width:220px"></label>
            <button class="btn btn-primary" type="submit">Turn on 2-step sign-in</button>
        </form>
        <p class="hint">Next you will get 10 recovery codes. Print them or write them down and keep them safe — they let you in if you lose your phone.</p>
        <?php if (!$required): ?><p><a href="<?= e(url('account/security')) ?>">Cancel</a></p><?php else: ?><form method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?><button class="btn btn-ghost" type="submit">Sign out and do this later</button></form><?php endif; ?>
    </div>
</div>
<script src="<?= e(asset('js/qrcode.js')) ?>"></script>
<script src="<?= e(asset('js/totp-qr.js')) ?>"></script>
