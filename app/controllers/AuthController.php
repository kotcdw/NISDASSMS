<?php
declare(strict_types=1);

class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            redirect('');
        }
        $this->view('auth/login', ['title' => 'Sign in', 'notices' => Notices::forLogin()], null);
    }

    public function login(): void
    {
        $email = (string) ($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        [$ok, $error, $next] = Auth::attempt($email, $password);
        if (!$ok) {
            with_old(['email' => $email]);
            flash('error', $error);
            redirect('login');
        }
        if ($next === 'mfa') {
            redirect('login/verify');
        }
        $this->afterLogin();
    }

    /** Second step: the 6-digit code from the authenticator app (or a recovery code). */
    public function showVerify(): void
    {
        if (empty($_SESSION['mfa_uid']) || ($_SESSION['mfa_at'] ?? 0) < time() - 300) {
            unset($_SESSION['mfa_uid']);
            flash('warning', 'Please sign in again.');
            redirect('login');
        }
        $this->view('auth/verify', ['title' => '2-step sign-in'], null);
    }

    public function verify(): void
    {
        $uid = (int) ($_SESSION['mfa_uid'] ?? 0);
        if (!$uid || ($_SESSION['mfa_at'] ?? 0) < time() - 300) {
            $_SESSION = [];
            flash('warning', 'That took too long. Please sign in again.');
            redirect('login');
        }
        $user = db()->one('SELECT * FROM users WHERE id = ? AND is_active = 1', [$uid]);
        $code = (string) ($_POST['code'] ?? '');
        if ($user && Security::verifySecondFactor($user, $code)) {
            Auth::completeLogin($user, true);
            $this->afterLogin();
        }
        $_SESSION['mfa_tries'] = ($_SESSION['mfa_tries'] ?? 0) + 1;
        db()->insert('login_attempts', ['identifier' => strtolower((string) ($user['email'] ?? 'mfa')), 'ip' => Security::clientIp(), 'attempted_at' => gmdate('Y-m-d H:i:s')]);
        if ($_SESSION['mfa_tries'] >= 5) {
            audit('login_2fa_failed', 'users', $uid);
            $_SESSION = [];
            flash('error', 'Too many wrong codes. Please sign in again.');
            redirect('login');
        }
        flash('error', 'That code is not right. Check the time on your phone and try the newest code.');
        redirect('login/verify');
    }

    private function afterLogin(): void
    {
        $user = Auth::user();
        if (!$user) {
            redirect('login');
        }
        if ((int) $user['must_change_password']) {
            flash('warning', !empty($_SESSION['pw_expired']) ? 'Your password has expired. Please choose a new one.' : 'Please set a new password before continuing.');
            redirect('account/password');
        }
        if (!empty($_SESSION['must_enrol_2fa'])) {
            redirect('account/2fa');
        }
        $intended = $_SESSION['_intended'] ?? '';
        unset($_SESSION['_intended']);
        // only follow internal route strings
        redirect(preg_match('#^[a-z0-9/_.-]*$#i', $intended) ? $intended : '');
    }

    public function logout(): void
    {
        Auth::logout();
        session_start();
        flash('success', 'You have signed out.');
        redirect('login');
    }

    public function showPassword(): void
    {
        $this->view('auth/password', ['title' => 'Change password', 'hints' => PasswordPolicy::hints(Auth::user())]);
    }

    public function changePassword(): void
    {
        $user = db()->one('SELECT * FROM users WHERE id = ?', [Auth::id()]);
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirmation'] ?? '');
        $errors = [];
        if (!password_verify($current, $user['password_hash'])) {
            $errors['current_password'] = 'Current password is incorrect.';
        } elseif ($new !== $confirm) {
            $errors['password_confirmation'] = 'Passwords do not match.';
        } elseif ($problems = PasswordPolicy::check($new, $user, $user['password_hash'])) {
            $errors['password'] = implode(' ', $problems);
        }
        if ($errors) {
            with_old([], $errors);
            redirect('account/password');
        }
        PasswordPolicy::set((int) $user['id'], $new);
        // Other devices signed in with the old password are signed out
        Security::revokeAll((int) $user['id'], 'password_changed', true);
        session_regenerate_id(true);
        unset($_SESSION['pw_expired']);
        audit('password_changed', 'users', (int) $user['id']);
        flash('success', 'Your password has been changed. Any other devices were signed out.');
        redirect('');
    }
}
