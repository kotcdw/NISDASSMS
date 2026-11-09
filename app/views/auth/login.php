<?php require __DIR__ . '/_top.php'; ?>
    <form method="post" action="<?= e(url('login')) ?>" class="form">
        <?= csrf_field() ?>
        <label>Email, phone number or admission no. <input type="text" name="email" required autofocus autocomplete="username" value="<?= e(old('email')) ?>"></label>
        <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
        <button class="btn btn-primary" type="submit" style="width:100%;justify-content:center">Sign in</button>
    </form>
    <p class="muted" style="font-size:.85rem;margin-top:1rem">Parents applying for admission: <a href="<?= e(url('apply')) ?>">apply online</a> · <a href="<?= e(url('privacy')) ?>">Privacy notice</a></p>
</main>
<?php if ($notices): ?>
<aside class="auth-card auth-notices" aria-labelledby="ln-title">
    <h2 id="ln-title">Notice board</h2>
    <?php foreach ($notices as $n): [$cat, $tone] = Notices::CATEGORIES[$n['category']] ?? ['Notice', 'info']; ?>
    <details class="ln-item">
        <summary><span class="badge badge-<?= e($tone) ?>"><?= e($cat) ?></span> <strong><?= e($n['title']) ?></strong><small><?= e(date('j M Y', strtotime($n['publish_at']))) ?></small></summary>
        <div class="ln-body"><?= nl2br(e($n['body'])) ?></div>
        <?php if ($n['attachment']): ?><p class="ln-file"><a href="<?= e(url('login/notices/' . $n['id'] . '/file')) ?>">⇩ <?= e($n['attachment_name'] ?: 'Attachment') ?></a></p><?php endif; ?>
    </details>
    <?php endforeach; ?>
</aside>
<?php endif; ?>
</div>
</body>
</html>
