<div class="slips">
    <?php foreach ($slips as $s): ?>
    <div class="slip">
        <strong><?= e(school_name()) ?></strong> · <?= e($s['who']) ?> sign-in<br>
        <span class="slip-name"><?= e($s['name']) ?></span>
        <table><tr><th>Website</th><td><?= e(absolute_url('login')) ?></td></tr><tr><th>Sign-in name</th><td><code><?= e($s['username']) ?></code></td></tr><tr><th>Temporary password</th><td><code><?= e($s['password']) ?></code></td></tr></table>
        <small>You will choose your own password after signing in. Keep it secret.</small>
    </div>
    <?php endforeach; ?>
</div>
