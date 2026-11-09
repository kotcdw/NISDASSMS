<?php
declare(strict_types=1);

/** Public side of the Form Builder: anyone with the link can fill an open form. */
class FillController extends Controller
{
    private const MAX_PER_HOUR_PER_IP = 20;

    public function show(string $token): void
    {
        $form = $this->find($token);
        $preview = !FormDef::isOpen($form) && can('forms.manage');
        $this->view('forms/fill', [
            'title' => $form['title'], 'form' => $form, 'fields' => FormDef::fields((int) $form['id']),
            'open' => FormDef::isOpen($form), 'preview' => $preview,
        ], $form['audience'] === 'staff' ? 'layouts/app' : 'layouts/public');
    }

    public function submit(string $token): void
    {
        $form = $this->find($token);
        if (!FormDef::isOpen($form)) {
            flash('error', 'This form is not accepting responses.');
            redirect('f/' . $token);
        }
        if (!empty($_POST['website'])) { // honeypot
            redirect('f/' . $token . '/done');
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if ((int) db()->value('SELECT COUNT(*) FROM form_responses WHERE ip = ? AND created_at > (UTC_TIMESTAMP() - INTERVAL 1 HOUR)', [$ip]) >= self::MAX_PER_HOUR_PER_IP) {
            flash('error', 'Too many submissions from this device. Please try again later.');
            redirect('f/' . $token);
        }

        $fields = FormDef::fields((int) $form['id']);
        [$values, $errors] = FormDef::validate($fields, $_POST, $_FILES);
        $name = mb_substr(trim((string) ($_POST['respondent_name'] ?? '')), 0, 150);
        $phone = (string) Admission::normalisePhone(trim((string) ($_POST['respondent_phone'] ?? '')));
        if ($form['collect_contact'] && $form['audience'] === 'public') {
            if ($name === '') {
                $errors['respondent_name'] = 'Your name is required.';
            }
            if (!SmsGateway::validPhone($phone)) {
                $errors['respondent_phone'] = 'Enter a valid phone number, e.g. 0244123456.';
            } elseif ($form['one_per_phone'] && db()->value('SELECT id FROM form_responses WHERE form_id = ? AND respondent_phone = ?', [$form['id'], $phone])) {
                $errors['respondent_phone'] = 'A response from this phone number has already been received.';
            }
        }
        if ($errors) {
            $old = ['respondent_name' => $name, 'respondent_phone' => $_POST['respondent_phone'] ?? ''];
            foreach ((array) ($_POST['f'] ?? []) as $k => $v) {
                $old['f' . (int) $k] = $v;
            }
            $errs = [];
            foreach ($errors as $k => $v) {
                $errs[is_int($k) ? 'f' . $k : $k] = $v;
            }
            with_old($old, $errs);
            flash('error', 'Please correct the highlighted answers.');
            redirect('f/' . $token);
        }

        // Store files after validation passes
        $stored = [];
        foreach ($fields as $f) {
            $fid = (int) $f['id'];
            if ($f['type'] !== 'file' || !isset($_FILES['f']['error'][$fid]) || $_FILES['f']['error'][$fid] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $file = [
                'name' => $_FILES['f']['name'][$fid], 'type' => $_FILES['f']['type'][$fid], 'tmp_name' => $_FILES['f']['tmp_name'][$fid],
                'error' => $_FILES['f']['error'][$fid], 'size' => $_FILES['f']['size'][$fid],
            ];
            [$info, $err] = Upload::store($file, 'forms');
            if ($err) {
                foreach ($stored as $s) {
                    @unlink(uploads_dir() . '/' . $s['stored_name']);
                }
                with_old(['respondent_name' => $name, 'respondent_phone' => $phone], ['f' . $fid => $err]);
                flash('error', $f['label'] . ': ' . $err);
                redirect('f/' . $token);
            }
            $stored[$fid] = $info;
        }

        $respId = db()->transaction(function (Database $d) use ($form, $values, $stored, $name, $phone) {
            $respId = $d->insert('form_responses', [
                'form_id' => $form['id'], 'user_id' => Auth::id(),
                'respondent_name' => $name !== '' ? $name : (Auth::user()['name'] ?? null),
                'respondent_phone' => $phone !== '' ? $phone : null, 'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
            foreach ($values as $fid => $v) {
                if ($v !== '') {
                    $d->insert('form_answers', ['response_id' => $respId, 'field_id' => $fid, 'value' => $v]);
                }
            }
            foreach ($stored as $fid => $info) {
                $d->insert('form_answers', [
                    'response_id' => $respId, 'field_id' => $fid, 'value' => null,
                    'file_name' => $info['original_name'], 'file_stored' => $info['stored_name'], 'file_mime' => $info['mime'],
                ]);
            }
            return $respId;
        });
        if ($form['created_by']) {
            $count = (int) db()->value('SELECT COUNT(*) FROM form_responses WHERE form_id = ?', [$form['id']]);
            if ($count === 1 || $count % 25 === 0) { // avoid flooding the bell
                Notifier::inapp([(int) $form['created_by']], "“{$form['title']}” has $count response" . ($count > 1 ? 's' : ''), null, 'forms/' . $form['id'] . '/responses');
            }
        }
        $_SESSION['form_done'][$token] = $respId;
        redirect('f/' . $token . '/done');
    }

    public function done(string $token): void
    {
        $form = $this->find($token);
        $this->view('forms/done', ['title' => $form['title'], 'form' => $form], $form['audience'] === 'staff' ? 'layouts/app' : 'layouts/public');
    }

    private function find(string $token): array
    {
        $form = db()->one('SELECT * FROM forms WHERE public_key = ?', [$token]) ?? $this->notFound();
        if ($form['audience'] === 'staff' && !can('staff')) {
            $_SESSION['_intended'] = 'f/' . $token;
            flash('info', 'Please sign in to fill this staff form.');
            redirect('login');
        }
        return $form;
    }
}
