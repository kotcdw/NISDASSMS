<div class="page-center">
<div class="card" style="max-width:460px;margin:2rem auto">
    <h1 style="margin-top:0">Confirm it is you</h1>
    <p class="muted">This page changes security or private records. Type your password<?= $needCode ? ' and a code from your authenticator app' : '' ?> again to continue.</p>
    <form method="post" action="<?= e(url('account/confirm')) ?>" class="form">
        <?= csrf_field() ?>
        <label>Password <input type="password" name="password" required autofocus autocomplete="current-password"></label>
        <?php if ($needCode): ?><label>6-digit code <input name="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="11"></label><?php endif; ?>
        <button class="btn btn-primary" type="submit">Continue</button>
        <a class="btn btn-ghost" href="<?= e(url('dashboard')) ?>">Cancel</a>
    </form>
</div>
</div>
