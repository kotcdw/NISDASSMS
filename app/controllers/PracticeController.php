<?php
declare(strict_types=1);

/** Administration → Practice copy: create or reset the training copy at /demo. */
class PracticeController extends Controller
{
    public function index(): void
    {
        $exists = is_demo() ? true : DemoSeeder::exists();
        $loginGroups = [];
        if ($exists) {
            $practiceDb = is_demo() ? db() : Database::connect(['name' => DemoSeeder::dbName()] + config('db'));
            $loginGroups = ['Administrators' => [], 'Teachers' => [], 'Students' => [], 'Parents' => []];
            foreach ($practiceDb->all('SELECT name, email, username, role FROM users ORDER BY name') as $user) {
                $group = match ($user['role']) {
                    'administrator' => 'Administrators',
                    'class_teacher', 'subject_teacher' => 'Teachers',
                    'pupil' => 'Students',
                    'parent' => 'Parents',
                    default => null,
                };
                if ($group !== null) {
                    $loginGroups[$group][] = [
                        'name' => $user['name'],
                        'identifier' => $user['username'] ?: $user['email'],
                    ];
                }
            }
        }
        $this->view('setup/practice', [
            'title' => 'Practice copy', 'exists' => $exists, 'dbName' => DemoSeeder::dbName(), 'loginGroups' => $loginGroups,
            'demoUrl' => is_demo() ? url('') : rtrim(app_base(), '/') . '/demo/',
        ]);
    }

    public function build(): void
    {
        if (is_demo()) {
            flash('error', 'Reset the practice copy from the real system (Administration → Practice copy).');
            redirect('practice');
        }
        if (trim((string) ($_POST['confirm'] ?? '')) !== 'PRACTICE') {
            flash('error', 'Type PRACTICE in the box to confirm.');
            redirect('practice');
        }
        try {
            $msg = DemoSeeder::build();
            audit('practice_copy_built', 'system');
            flash('success', $msg);
        } catch (Throwable $e) {
            error_log('[NISDAS] practice copy: ' . $e->getMessage());
            flash('error', 'The practice copy could not be created: ' . $e->getMessage());
        }
        redirect('practice');
    }

    public function remove(): void
    {
        if (is_demo()) {
            redirect('practice');
        }
        try {
            DemoSeeder::remove();
            audit('practice_copy_removed', 'system');
            flash('success', 'The practice copy has been removed. Its address now shows "not created yet".');
        } catch (Throwable $e) {
            flash('error', 'Could not remove it: ' . $e->getMessage());
        }
        redirect('practice');
    }
}
