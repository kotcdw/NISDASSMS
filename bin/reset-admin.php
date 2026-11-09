<?php
/**
 * Forgotten Super Admin password? Run on the school computer:
 *   C:\xampp\php\php.exe C:\xampp\htdocs\NISDASSMS\bin\reset-admin.php
 * It shows the Super Admin sign-in email(s), lets you type a new password and clears any lock-out.
 * Only someone with access to the computer itself can run it.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Run from the command line.\n");
}
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

$admins = db()->all("SELECT id, name, email, is_active FROM users WHERE role = 'super_admin' ORDER BY id");
if (!$admins) {
    exit("No Super Admin account found.\n");
}
echo "Super Admin account(s):\n";
foreach ($admins as $i => $a) {
    echo '  ' . ($i + 1) . ') ' . $a['email'] . '  (' . $a['name'] . ($a['is_active'] ? '' : ', switched off') . ")\n";
}
echo "\nNumber of the account to reset (or press Enter to stop): ";
$n = (int) trim((string) fgets(STDIN));
if ($n < 1 || $n > count($admins)) {
    exit("Nothing changed.\n");
}
$a = $admins[$n - 1];
$full = db()->one('SELECT * FROM users WHERE id = ?', [$a['id']]);
echo "Password rules:\n  - " . implode("\n  - ", PasswordPolicy::hints($full)) . "\n";
echo 'New password for ' . $a['email'] . ': ';
$p1 = rtrim((string) fgets(STDIN), "\r\n");
echo 'Type it again: ';
$p2 = rtrim((string) fgets(STDIN), "\r\n");
if ($p1 !== $p2) {
    exit("The passwords do not match. Nothing changed.\n");
}
if ($problems = PasswordPolicy::check($p1, $full)) {
    exit(implode("\n", $problems) . "\nNothing changed.\n");
}
PasswordPolicy::set((int) $a['id'], $p1);
db()->update('users', ['is_active' => 1] + (Security::ready() ? ['locked_reason' => null] : []), 'id = ?', [$a['id']]);
db()->run('DELETE FROM login_attempts WHERE identifier = ?', [$a['email']]);
Security::revokeAll((int) $a['id'], 'password_reset');
audit('admin_password_reset_cli', 'users', (int) $a['id']);
if (!empty($full['totp_enabled_at'])) {
    echo "\n2-step sign-in is on for this account. Lost the phone? Type RESET to switch it off (you will set it up again at sign-in), or press Enter to keep it: ";
    if (trim((string) fgets(STDIN)) === 'RESET') {
        db()->update('users', ['totp_secret' => null, 'totp_enabled_at' => null, 'totp_last_step' => 0, 'recovery_codes' => null], 'id = ?', [$a['id']]);
        audit('2fa_reset_cli', 'users', (int) $a['id']);
        echo "2-step sign-in switched off.\n";
    }
}
echo "\nDone. Sign in at http://localhost/NISDASSMS with " . $a['email'] . " and the new password.\n";
