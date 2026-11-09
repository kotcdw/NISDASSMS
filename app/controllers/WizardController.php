<?php
declare(strict_types=1);

/** "Configure the school" wizard: one page listing everything to set up, in order. */
class WizardController extends Controller
{
    private const ACTIONS = [
        'create_year' => 'createYear', 'assign_subjects' => 'assignSubjects', 'class_teachers_teach' => 'classTeachersTeach',
        'confirm_grading' => 'confirmGrading', 'payroll_from_users' => 'payrollFromUsers',
    ];

    public function index(): void
    {
        $steps = SetupWizard::steps();
        [$done, $total] = SetupWizard::progress($steps);
        $next = null;
        foreach ($steps as $s) {
            if ($s['done'] === false) {
                $next = $s;
                break;
            }
        }
        $this->view('setup/wizard', ['title' => 'Configure the school', 'steps' => $steps, 'done' => $done, 'total' => $total, 'next' => $next]);
    }

    public function run(): void
    {
        $a = (string) ($_POST['action'] ?? '');
        if (!isset(self::ACTIONS[$a])) {
            redirect('setup/wizard');
        }
        if ($a === 'payroll_from_users' && !can('payroll.manage') && !can('users.manage')) {
            flash('error', 'Only the Bursar or Super Admin can create pay records.');
            redirect('setup/wizard');
        }
        $msg = SetupWizard::{self::ACTIONS[$a]}();
        flash('success', $msg);
        redirect('setup/wizard');
    }

    /** "Check SMS account" button (Profile → App settings → Messaging settings). */
    public function smsBalance(): void
    {
        $r = SmsGateway::balance();
        audit('sms_balance_checked', 'settings', null, ['ok' => $r['ok']]);
        flash($r['ok'] ? 'success' : 'error', $r['message']);
        redirect('messages/settings');
    }

    /** "Test connection" button (Profile → App settings → Fee settings). */
    public function paymentTest(): void
    {
        $r = PaymentGateway::testConnection();
        audit('payment_connection_tested', 'settings', null, ['ok' => $r['ok'], 'gateway' => PaymentGateway::active()]);
        flash($r['ok'] ? 'success' : 'error', $r['message']);
        redirect('fees/settings');
    }
}
