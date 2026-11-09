<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($title ?? '') ? $title . ' · ' : '') ?><?= e(school_name()) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<?= favicon_tags() ?>
</head>
<body class="public-page<?= bg_art_url() !== '' ? ' public-bg' : '' ?>">
<?php if (bg_art_url() !== ''): ?><div class="auth-bg" aria-hidden="true" style="background-image:url('<?= e(bg_art_url()) ?>')"></div><div class="public-shade" aria-hidden="true"></div><?php endif; ?>
<?php if (is_demo()): ?><div class="demo-banner">PRACTICE COPY — training only. Every practice login uses the password <?= e(DemoSeeder::PASSWORD) ?> (see Administration → Practice copy in the real system).</div><?php endif; ?>
<header class="public-header">
    <a class="brand" href="<?= e(url('apply')) ?>">
        <img class="brand-logo" src="<?= e(app_logo_url()) ?>" alt="<?= e(school_name()) ?> logo">
        <span><strong><?= e(school_name()) ?></strong><small><?= e(setting('school_motto', 'Seventh-day Adventist Basic School')) ?></small></span>
    </a>
    <nav>
        <a href="<?= e(url('apply')) ?>">Apply</a>
        <a href="<?= e(url('apply/status')) ?>">Check application</a>
        <a href="<?= e(url('login')) ?>">Staff sign in</a>
    </nav>
</header>
<main class="public-content">
    <?php foreach (take_flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
</main>
<footer class="public-footer">© <?= date('Y') ?> <?= e(school_name()) ?> · <?= e(setting('school_phone')) ?> · <a href="<?= e(url('privacy')) ?>">Privacy notice</a></footer>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
