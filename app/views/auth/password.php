<div class="page-head"><div><h1>Change password</h1></div></div>
<div class="card" style="max-width:520px">
    <form method="post" action="<?= e(url('account/password')) ?>" class="form">
        <?= csrf_field() ?>
        <label>Current password <input type="password" name="current_password" required autocomplete="current-password"><?= field_error('current_password') ?></label>
        <label>New password <input type="password" name="password" required minlength="6" autocomplete="new-password"><?= field_error('password') ?></label>
        <label>Confirm new password <input type="password" name="password_confirmation" required minlength="6" autocomplete="new-password"><?= field_error('password_confirmation') ?></label>
        <ul class="hint" style="margin:.2rem 0 .8rem;padding-left:1.1rem"><?php foreach ($hints ?? [] as $h): ?><li><?= e($h) ?></li><?php endforeach; ?></ul>
        <button class="btn btn-primary" type="submit">Save password</button>
    </form>
</div>
