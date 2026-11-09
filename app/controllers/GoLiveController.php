<?php
declare(strict_types=1);

/** Go-live checklist: automatic readiness checks, decisions to confirm and the trial-run script. */
class GoLiveController extends Controller
{
    public function index(): void
    {
        $checks = GoLive::checks();
        $ticks = GoLive::ticks();
        $failed = count(array_filter($checks, fn($c) => $c['ok'] === false));
        $warn = count(array_filter($checks, fn($c) => $c['ok'] === null));
        $confirmed = count(array_filter(array_keys(GoLive::CONFIRMATIONS), fn($k) => isset($ticks['confirm:' . $k]['done_at'])));
        $trialDone = count(array_filter(GoLive::allTrialKeys(), fn($k) => isset($ticks[$k]['done_at'])));
        $view = isset($_GET['print']) ? 'golive/print' : 'golive/index';
        $this->view($view, [
            'title' => 'Go-live checklist', 'checks' => $checks, 'ticks' => $ticks, 'failed' => $failed, 'warn' => $warn,
            'confirmed' => $confirmed, 'trialDone' => $trialDone, 'trialTotal' => count(GoLive::allTrialKeys()),
        ], isset($_GET['print']) ? 'layouts/print' : 'layouts/app');
    }

    public function tick(): void
    {
        $key = (string) ($_POST['key'] ?? '');
        $valid = array_merge(array_map(fn($k) => 'confirm:' . $k, array_keys(GoLive::CONFIRMATIONS)), GoLive::allTrialKeys());
        if (!in_array($key, $valid, true)) {
            $this->notFound();
        }
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 255) ?: null;
        if (!empty($_POST['done'])) {
            db()->run(
                'INSERT INTO golive_checks (check_key, done_by, done_at, note) VALUES (?, ?, NOW(), ?) ON DUPLICATE KEY UPDATE done_by = VALUES(done_by), done_at = VALUES(done_at), note = VALUES(note)',
                [$key, Auth::id(), $note]
            );
        } else {
            db()->run('INSERT INTO golive_checks (check_key, done_by, done_at, note) VALUES (?, NULL, NULL, ?) ON DUPLICATE KEY UPDATE done_by = NULL, done_at = NULL, note = VALUES(note)', [$key, $note]);
        }
        audit('golive_tick', 'golive_checks', null, ['key' => $key, 'done' => !empty($_POST['done'])]);
        header('Location: ' . url('golive') . '#' . str_replace(':', '-', $key));
        exit;
    }
}
