<?php
declare(strict_types=1);

/** "My security": 2-step sign-in, recovery codes, signed-in devices, and password confirmation for risky pages. */
class AccountController extends Controller
{
    public function security(): void
    {
        $u = db()->one('SELECT * FROM users WHERE id = ?', [Auth::id()]);
        $this->view('account/security', [
            'title' => 'My security', 'u' => $u, 'sessions' => Security::sessions((int) $u['id']),
            'enabled' => Security::totpEnabled($u), 'required' => Security::mfaRequired($u),
            'codesLeft' => count(json_decode((string) ($u['recovery_codes'] ?? '[]'), true) ?: []),
            'newCodes' => $this->takeCodes(),
        ]);
    }

    /** Set up 2-step sign-in: show the QR code, then confirm with a first code. */
    public function setup2fa(): void
    {
        $u = db()->one('SELECT * FROM users WHERE id = ?', [Auth::id()]);
        if (!Security::ready()) {
            flash('warning', 'Click "Update database now" first.');
            redirect('dashboard');
        }
        if (Security::totpEnabled($u)) {
            redirect('account/security');
        }
        if (empty($_SESSION['totp_new']) || ($_SESSION['totp_new_at'] ?? 0) < time() - 900) {
            $_SESSION['totp_new'] = Totp::newSecret();
            $_SESSION['totp_new_at'] = time();
        }
        $secret = $_SESSION['totp_new'];
        $this->view('account/twofa', [
            'title' => 'Set up 2-step sign-in', 'secret' => $secret, 'required' => !empty($_SESSION['must_enrol_2fa']) || Security::mustEnrol(Auth::user()),
            'uri' => Totp::uri($secret, (string) ($u['email'] ?: $u['username']), setting('school_short_name', 'NISDAS') . ' SMS'),
        ]);
    }

    public function enable2fa(): void
    {
        $u = db()->one('SELECT * FROM users WHERE id = ?', [Auth::id()]);
        $secret = (string) ($_SESSION['totp_new'] ?? '');
        $step = $secret !== '' ? Totp::verify($secret, (string) ($_POST['code'] ?? '')) : null;
        if ($step === null) {
            flash('error', 'That code did not match. Make sure the phone\'s time is set automatically, then type the newest 6-digit code.');
            redirect('account/2fa');
        }
        [$plain, $hashes] = Security::newRecoveryCodes();
        db()->update('users', [
            'totp_secret' => Crypto::encrypt($secret), 'totp_enabled_at' => gmdate('Y-m-d H:i:s'), 'totp_last_step' => $step,
            'recovery_codes' => json_encode($hashes),
        ], 'id = ?', [$u['id']]);
        unset($_SESSION['totp_new'], $_SESSION['totp_new_at'], $_SESSION['must_enrol_2fa']);
        $_SESSION['mfa'] = true;
        // Other sessions were signed in without the second step: sign them out
        Security::revokeAll((int) $u['id'], '2fa_enabled', true);
        $_SESSION['show_codes'] = $plain;
        audit('2fa_enabled', 'users', (int) $u['id']);
        flash('success', '2-step sign-in is on. Print or write down your recovery codes now — they are shown only once.');
        redirect('account/security');
    }

    public function disable2fa(): void
    {
        $u = db()->one('SELECT * FROM users WHERE id = ?', [Auth::id()]);
        if (Security::mfaRequired($u)) {
            flash('error', 'Your role requires 2-step sign-in, so it cannot be switched off.');
            redirect('account/security');
        }
        if (!password_verify((string) ($_POST['password'] ?? ''), $u['password_hash']) || !Security::verifySecondFactor($u, (string) ($_POST['code'] ?? ''))) {
            flash('error', 'Password or code is not right. Nothing changed.');
            redirect('account/security');
        }
        db()->update('users', ['totp_secret' => null, 'totp_enabled_at' => null, 'totp_last_step' => 0, 'recovery_codes' => null], 'id = ?', [$u['id']]);
        audit('2fa_disabled', 'users', (int) $u['id']);
        flash('success', '2-step sign-in is off.');
        redirect('account/security');
    }

    public function newCodes(): void
    {
        $u = db()->one('SELECT * FROM users WHERE id = ?', [Auth::id()]);
        if (!Security::totpEnabled($u) || !Security::verifySecondFactor($u, (string) ($_POST['code'] ?? ''))) {
            flash('error', 'Type a current 6-digit code from your app to make new recovery codes.');
            redirect('account/security');
        }
        [$plain, $hashes] = Security::newRecoveryCodes();
        db()->update('users', ['recovery_codes' => json_encode($hashes)], 'id = ?', [$u['id']]);
        $_SESSION['show_codes'] = $plain;
        audit('2fa_recovery_codes_renewed', 'users', (int) $u['id']);
        flash('success', 'New recovery codes made. The old ones no longer work.');
        redirect('account/security');
    }

    public function revokeSession(int $id): void
    {
        if (Security::revokeOne((int) Auth::id(), $id, 'signed_out_by_user')) {
            audit('session_revoked', 'users', Auth::id(), ['session' => $id]);
            flash('success', 'That device was signed out.');
        }
        redirect('account/security');
    }

    public function revokeOthers(): void
    {
        $n = Security::revokeAll((int) Auth::id(), 'signed_out_by_user', true);
        audit('sessions_revoked_others', 'users', Auth::id(), ['count' => $n]);
        flash('success', $n ? "$n other device(s) signed out." : 'No other devices were signed in.');
        redirect('account/security');
    }

    // ------------------------------------------------------------------ step-up

    public function showConfirm(): void
    {
        $u = db()->one('SELECT * FROM users WHERE id = ?', [Auth::id()]);
        $this->view('account/confirm', ['title' => 'Confirm it is you', 'needCode' => Security::totpEnabled($u)]);
    }

    public function confirm(): void
    {
        $u = db()->one('SELECT * FROM users WHERE id = ?', [Auth::id()]);
        $ok = password_verify((string) ($_POST['password'] ?? ''), $u['password_hash'])
            && (!Security::totpEnabled($u) || Security::verifySecondFactor($u, (string) ($_POST['code'] ?? '')));
        if (!$ok) {
            $_SESSION['confirm_fails'] = ($_SESSION['confirm_fails'] ?? 0) + 1;
            audit('step_up_failed', 'users', (int) $u['id']);
            if ($_SESSION['confirm_fails'] >= 5) {
                Auth::logout('step_up_failed');
                session_start();
                flash('error', 'Too many wrong attempts. Please sign in again.');
                redirect('login');
            }
            flash('error', 'That is not right. Try again.');
            redirect('account/confirm');
        }
        $_SESSION['confirmed_at'] = time();
        unset($_SESSION['confirm_fails']);
        $back = (string) ($_SESSION['confirm_return'] ?? 'dashboard');
        unset($_SESSION['confirm_return']);
        header('Location: ' . url('') . ltrim($back, '/'));
        exit;
    }

    private function takeCodes(): array
    {
        $c = $_SESSION['show_codes'] ?? [];
        unset($_SESSION['show_codes']);
        return $c;
    }
}
