<?php
/**
 * One-time installer: creates the database, tables, default data and the first
 * Super Admin account. Locks itself (storage/installed.lock) when finished.
 */
declare(strict_types=1);

define('BASE_PATH', __DIR__);
require BASE_PATH . '/app/bootstrap.php';

if (is_installed()) {
    header('Location: ' . url('login'));
    exit;
}

$errors = [];
$checks = [
    'PHP 8.0 or newer'          => version_compare(PHP_VERSION, '8.0.0', '>='),
    'PDO MySQL extension'       => extension_loaded('pdo_mysql'),
    'Fileinfo extension'        => extension_loaded('fileinfo'),
    'storage/ folder writable'  => is_writable(BASE_PATH . '/storage'),
];
$db = config('db');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verifyOrFail();
    $in = [
        'school_name' => trim($_POST['school_name'] ?? ''),
        'name'        => trim($_POST['name'] ?? ''),
        'email'       => strtolower(trim($_POST['email'] ?? '')),
        'password'    => (string) ($_POST['password'] ?? ''),
        'password2'   => (string) ($_POST['password2'] ?? ''),
        'year_name'   => trim($_POST['year_name'] ?? ''),
        'year_start'  => trim($_POST['year_start'] ?? ''),
        'year_end'    => trim($_POST['year_end'] ?? ''),
    ];
    if ($in['school_name'] === '') $errors[] = 'School name is required.';
    if ($in['name'] === '') $errors[] = 'Administrator name is required.';
    if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid administrator email is required.';
    foreach (PasswordPolicy::check($in['password'], ['name' => $in['name'], 'email' => $in['email'], 'role' => 'super_admin']) as $pwErr) {
        $errors[] = 'Password: ' . $pwErr;
    }
    if ($in['password'] !== $in['password2']) $errors[] = 'Passwords do not match.';
    if ($in['year_name'] !== '' && (!Validator::isDate($in['year_start']) || !Validator::isDate($in['year_end']) || $in['year_end'] <= $in['year_start'])) {
        $errors[] = 'Academic year needs a valid start and end date.';
    }
    foreach ($checks as $label => $ok) {
        if (!$ok) $errors[] = "Requirement not met: $label.";
    }

    if (!$errors) {
        try {
            $server = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $db['host'], (int) $db['port']), $db['user'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            if (!preg_match('/^\w+$/', $db['name'])) {
                throw new RuntimeException('Database name may only contain letters, numbers and underscores.');
            }
            try {
                $server->exec("CREATE DATABASE IF NOT EXISTS `{$db['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            } catch (PDOException $e) {
                // Hosting accounts (cPanel) usually forbid CREATE DATABASE: the database must be created
                // in the control panel first; carry on if it already exists and we can use it.
                $server->exec("USE `{$db['name']}`");
            }

            $pdo = db()->pdo();
            Migrator::run($pdo);

            db()->transaction(function (Database $d) use ($in) {
                $d->run('UPDATE settings SET `value` = ? WHERE `key` = ?', [$in['school_name'], 'school_name']);
                $d->insert('users', [
                    'name'          => $in['name'],
                    'email'         => $in['email'],
                    'role'          => 'super_admin',
                    'password_hash' => password_hash($in['password'], PASSWORD_DEFAULT),
                    'password_changed_at' => gmdate('Y-m-d H:i:s'),
                ]);
                if ($in['year_name'] !== '') {
                    $yid = $d->insert('academic_years', [
                        'name' => $in['year_name'], 'start_date' => $in['year_start'], 'end_date' => $in['year_end'], 'is_current' => 1,
                    ]);
                    // Three placeholder terms splitting the year evenly; adjust in Setup → Academic years
                    $start = new DateTime($in['year_start']);
                    $end = new DateTime($in['year_end']);
                    $days = (int) $start->diff($end)->days;
                    for ($i = 1; $i <= 3; $i++) {
                        $ts = (clone $start)->modify('+' . (int) floor(($i - 1) * $days / 3) . ' days');
                        $te = $i === 3 ? clone $end : (clone $start)->modify('+' . ((int) floor($i * $days / 3) - 1) . ' days');
                        $d->insert('terms', [
                            'academic_year_id' => $yid, 'term_no' => $i, 'name' => "Term $i",
                            'start_date' => $ts->format('Y-m-d'), 'end_date' => $te->format('Y-m-d'), 'is_current' => $i === 1 ? 1 : 0,
                        ]);
                    }
                }
            });

            if (!is_dir(BASE_PATH . '/storage/uploads')) {
                mkdir(BASE_PATH . '/storage/uploads', 0775, true);
            }
            file_put_contents(BASE_PATH . '/storage/installed.lock', date('c'));
            flash('success', 'Installation complete. Sign in with your administrator account.');
            header('Location: ' . url('login'));
            exit;
        } catch (Throwable $e) {
            error_log('[NISDAS install] ' . $e->getMessage());
            $errors[] = 'Installation failed: ' . $e->getMessage();
        }
    }
}
$v = fn($k, $d = '') => e($_POST[$k] ?? $d);
$y = (int) date('Y');
$defaultYear = (int) date('n') >= 8 ? "$y/" . ($y + 1) : ($y - 1) . "/$y";
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install — Naylor International S.D.A School</title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<?= favicon_tags() ?>
</head>
<body class="auth-page">
<main class="auth-card wide">
    <h1>Install NISDAS SMS</h1>
    <p class="muted">This sets up the database <strong><?= e($db['name']) ?></strong> on <?= e($db['host']) ?> and creates the first Super Admin.</p>

    <ul class="checklist">
        <?php foreach ($checks as $label => $ok): ?>
            <li class="<?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? '✓' : '✗' ?> <?= e($label) ?></li>
        <?php endforeach; ?>
    </ul>

    <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" class="form">
        <?= csrf_field() ?>
        <fieldset>
            <legend>School</legend>
            <label>School name <input name="school_name" required value="<?= $v('school_name', 'NISDAS') ?>"></label>
        </fieldset>
        <fieldset>
            <legend>Super Admin account</legend>
            <label>Full name <input name="name" required value="<?= $v('name') ?>"></label>
            <label>Email <input type="email" name="email" required value="<?= $v('email') ?>"></label>
            <div class="grid-2">
                <label>Password <input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
                <label>Confirm password <input type="password" name="password2" required minlength="8" autocomplete="new-password"></label>
            </div>
        </fieldset>
        <fieldset>
            <legend>Current academic year (optional)</legend>
            <div class="grid-3">
                <label>Name <input name="year_name" placeholder="2026/2027" value="<?= $v('year_name', $defaultYear) ?>"></label>
                <label>Starts <input type="date" name="year_start" value="<?= $v('year_start') ?>"></label>
                <label>Ends <input type="date" name="year_end" value="<?= $v('year_end') ?>"></label>
            </div>
            <p class="hint">Three terms are created automatically; set the exact GES term dates afterwards in Setup.</p>
        </fieldset>
        <button class="btn btn-primary" type="submit">Install</button>
    </form>
</main>
</body>
</html>
