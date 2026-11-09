<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($title ?? 'Report') ?> · <?= e(school_name()) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/report.css')) ?>">
<?= favicon_tags() ?>
</head>
<body>
<div class="toolbar no-print">
    <button type="button" data-print>Print / Save as PDF</button>
</div>
<?= $content ?>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php if (str_contains($content, 'data-doc-qr')): ?><script src="<?= e(asset('js/qrcode.js')) ?>"></script><script src="<?= e(asset('js/doc-qr.js')) ?>"></script><?php endif; ?>
</body>
</html>
