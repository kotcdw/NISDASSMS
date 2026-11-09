<?php /* Shared top of the sign-in pages (login, 2-step code). */ ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · <?= e(school_name()) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<?= favicon_tags() ?>
</head>
<body class="auth-page<?= bg_art_url() !== '' ? ' auth-hero' : '' ?>">
<?php if (bg_art_url() !== ''): ?>
<div class="auth-bg" aria-hidden="true" style="background-image:url('<?= e(bg_art_url()) ?>')"></div>
<div class="auth-shade" aria-hidden="true"></div>
<p class="auth-motto"><?= e(setting('school_motto') ?: 'Learning, growing and playing together') ?></p>
<?php endif; ?>
<div class="auth-wrap">
<main class="auth-card">
    <div style="display:flex;gap:.7rem;align-items:center;margin-bottom:1.25rem">
        <img class="brand-logo" src="<?= e(app_logo_url()) ?>" alt="<?= e(school_name()) ?> logo">
        <div><h1 style="margin:0"><?= e(school_name()) ?></h1></div>
    </div>
    <?php foreach (take_flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
