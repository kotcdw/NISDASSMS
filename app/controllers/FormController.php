<?php
declare(strict_types=1);

/** Form Builder (staff side): build forms, read responses, share links. */
class FormController extends Controller
{
    public function index(): void
    {
        $forms = db()->all(
            'SELECT f.*, u.name AS author, (SELECT COUNT(*) FROM form_responses r WHERE r.form_id = f.id) AS responses,
                    (SELECT COUNT(*) FROM form_fields x WHERE x.form_id = f.id AND x.is_archived = 0 AND x.type <> \'section\') AS fields
             FROM forms f LEFT JOIN users u ON u.id = f.created_by ORDER BY f.status = \'closed\', f.id DESC'
        );
        $this->view('forms/index', ['title' => 'Form Builder', 'forms' => $forms]);
    }

    public function store(): void
    {
        $in = $this->only(['title', 'audience', 'template']);
        if (!$in['title'] || mb_strlen((string) $in['title']) > 150) {
            flash('error', 'Give the form a title (up to 150 characters).');
            redirect('forms');
        }
        $id = db()->transaction(function (Database $d) use ($in) {
            $id = $d->insert('forms', [
                'title' => $in['title'], 'audience' => $in['audience'] === 'staff' ? 'staff' : 'public',
                'public_key' => bin2hex(random_bytes(12)), 'created_by' => Auth::id(),
                'confirmation_message' => 'Thank you. Your response has been received.',
            ]);
            foreach ($this->starterFields((string) $in['template']) as $i => $f) {
                $d->insert('form_fields', $f + ['form_id' => $id, 'sort_order' => $i + 1]);
            }
            return $id;
        });
        audit('form_created', 'forms', $id, ['title' => $in['title']]);
        redirect('forms/' . $id);
    }

    public function builder(int $id): void
    {
        $form = $this->find($id);
        $answered = array_column(db()->all('SELECT DISTINCT a.field_id FROM form_answers a JOIN form_responses r ON r.id = a.response_id WHERE r.form_id = ?', [$id]), 'field_id', 'field_id');
        $this->view('forms/builder', [
            'title' => $form['title'], 'form' => $form, 'fields' => FormDef::fields($id), 'answered' => $answered,
            'responses' => (int) db()->value('SELECT COUNT(*) FROM form_responses WHERE form_id = ?', [$id]),
            'audiences' => can('messages.send') ? Audience::options() : [],
        ]);
    }

    public function update(int $id): void
    {
        $form = $this->find($id);
        $in = $this->only(['title', 'description', 'audience', 'status', 'closes_on', 'confirmation_message']);
        $in['collect_contact'] = isset($_POST['collect_contact']) ? 1 : 0;
        $in['one_per_phone'] = isset($_POST['one_per_phone']) ? 1 : 0;
        $errors = Validator::check($in, [
            'title'                => 'required|max:150',
            'audience'             => 'required|in:public,staff',
            'status'               => 'required|in:draft,published,closed',
            'closes_on'            => 'date',
            'confirmation_message' => 'max:255',
        ]);
        if (!$errors && $in['status'] === 'published' && !db()->value("SELECT COUNT(*) FROM form_fields WHERE form_id = ? AND is_archived = 0 AND type <> 'section'", [$id])) {
            $errors['status'] = 'Add at least one question before opening the form.';
        }
        if ($in['one_per_phone'] && !$in['collect_contact']) {
            $in['collect_contact'] = 1; // need the phone to enforce it
        }
        if ($errors) {
            flash('error', reset($errors));
            redirect('forms/' . $id);
        }
        db()->update('forms', $in, 'id = ?', [$id]);
        audit('form_updated', 'forms', $id, ['status' => $in['status']]);
        flash('success', $in['status'] === 'published' && $form['status'] !== 'published' ? 'Form is open. Share the link below.' : 'Form saved.');
        redirect('forms/' . $id);
    }

    public function addField(int $id): void
    {
        $this->find($id);
        $data = $this->fieldInput($id);
        $data['form_id'] = $id;
        $data['sort_order'] = (int) db()->value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM form_fields WHERE form_id = ?', [$id]);
        db()->insert('form_fields', $data);
        flash('success', 'Question added.');
        redirect('forms/' . $id);
    }

    public function updateField(int $id, int $field): void
    {
        $this->find($id);
        $this->fieldOr404($id, $field);
        db()->update('form_fields', $this->fieldInput($id), 'id = ? AND form_id = ?', [$field, $id]);
        flash('success', 'Question saved.');
        redirect('forms/' . $id);
    }

    public function moveField(int $id, int $field): void
    {
        $this->find($id);
        $this->fieldOr404($id, $field);
        $fields = FormDef::fields($id);
        $ids = array_map(fn($f) => (int) $f['id'], $fields);
        $i = array_search($field, $ids, true);
        $j = ($_POST['dir'] ?? '') === 'up' ? $i - 1 : $i + 1;
        if ($i !== false && isset($ids[$j])) {
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
            db()->transaction(function (Database $d) use ($ids, $id) {
                foreach ($ids as $pos => $fid) {
                    $d->update('form_fields', ['sort_order' => $pos + 1], 'id = ? AND form_id = ?', [$fid, $id]);
                }
            });
        }
        redirect('forms/' . $id);
    }

    public function deleteField(int $id, int $field): void
    {
        $this->find($id);
        $this->fieldOr404($id, $field);
        $hasAnswers = db()->value('SELECT COUNT(*) FROM form_answers WHERE field_id = ?', [$field]);
        if ($hasAnswers) {
            // Keep old answers readable: hide the question instead of deleting it
            db()->update('form_fields', ['is_archived' => 1], 'id = ?', [$field]);
            flash('success', 'Question removed from the form. Earlier answers are kept in the responses.');
        } else {
            db()->run('DELETE FROM form_fields WHERE id = ? AND form_id = ?', [$field, $id]);
            flash('success', 'Question deleted.');
        }
        redirect('forms/' . $id);
    }

    public function duplicate(int $id): void
    {
        $form = $this->find($id);
        $newId = db()->transaction(function (Database $d) use ($form, $id) {
            $newId = $d->insert('forms', [
                'title' => mb_substr('Copy of ' . $form['title'], 0, 150), 'description' => $form['description'],
                'public_key' => bin2hex(random_bytes(12)), 'audience' => $form['audience'], 'status' => 'draft',
                'collect_contact' => $form['collect_contact'], 'one_per_phone' => $form['one_per_phone'],
                'confirmation_message' => $form['confirmation_message'], 'created_by' => Auth::id(),
            ]);
            foreach (FormDef::fields($id) as $f) {
                $d->insert('form_fields', [
                    'form_id' => $newId, 'label' => $f['label'], 'type' => $f['type'], 'options' => $f['options'],
                    'is_required' => $f['is_required'], 'help_text' => $f['help_text'], 'sort_order' => $f['sort_order'],
                ]);
            }
            return $newId;
        });
        audit('form_duplicated', 'forms', $newId, ['from' => $id]);
        flash('success', 'Form copied. It is a draft until you open it.');
        redirect('forms/' . $newId);
    }

    /** Send the form link to parents/staff by SMS. */
    public function share(int $id): void
    {
        $form = $this->find($id);
        if (!FormDef::isOpen($form) || $form['audience'] !== 'public') {
            flash('error', 'Only open forms for parents/public can be shared by SMS.');
            redirect('forms/' . $id);
        }
        $audience = (string) ($_POST['audience'] ?? '');
        $intro = mb_substr(trim((string) ($_POST['intro'] ?? '')), 0, 300);
        if (!isset(Audience::options()[$audience]) || $intro === '') {
            flash('error', 'Choose who to send to and write a short message.');
            redirect('forms/' . $id);
        }
        $phones = Audience::phones($audience, (string) ($_POST['custom'] ?? ''));
        if (!$phones) {
            flash('error', 'No valid phone numbers found for this group.');
            redirect('forms/' . $id);
        }
        $body = $intro . ' ' . FormDef::publicUrl($form);
        $batch = db()->transaction(function (Database $d) use ($form, $audience, $phones, $body) {
            $batch = $d->insert('message_batches', [
                'title' => 'Form: ' . mb_substr($form['title'], 0, 140), 'audience' => $audience,
                'audience_label' => Audience::label($audience), 'body' => $body, 'recipients' => count($phones), 'created_by' => Auth::id(),
            ]);
            foreach ($phones as $phone => $name) {
                Notifier::sms($phone, $body, ['name' => $name, 'event' => 'form_link', 'entity' => 'forms', 'entity_id' => $form['id'], 'batch_id' => $batch]);
            }
            return $batch;
        });
        audit('form_shared', 'forms', $id, ['recipients' => count($phones)]);
        $r = Notifier::process(100);
        flash('success', count($phones) . ' SMS queued with the form link' . ($r['held'] ? ' (held until after the Sabbath).' : '.'));
        redirect('messages/outbox', ['batch' => $batch]);
    }

    // ---- Responses -------------------------------------------------------------------

    public function responses(int $id): void
    {
        $form = $this->find($id);
        $fields = array_values(array_filter(FormDef::fields($id, true), fn($f) => $f['type'] !== 'section'));
        [$rows, $pager] = paginate('SELECT r.*, u.name AS user_name FROM form_responses r LEFT JOIN users u ON u.id = r.user_id WHERE r.form_id = ? ORDER BY r.id DESC', [$id], 50);
        $answers = $this->answersFor(array_column($rows, 'id'));

        // Tallies for choice questions
        $tallies = [];
        foreach ($fields as $f) {
            if (!in_array($f['type'], FormDef::CHOICE_TYPES, true) || $f['is_archived']) {
                continue;
            }
            $counts = array_fill_keys(FormDef::options($f['options']), 0);
            foreach (db()->all('SELECT a.value FROM form_answers a JOIN form_responses r ON r.id = a.response_id WHERE r.form_id = ? AND a.field_id = ?', [$id, $f['id']]) as $a) {
                $vals = $f['type'] === 'checkbox' ? (json_decode((string) $a['value'], true) ?: []) : [$a['value']];
                foreach ($vals as $v) {
                    if (isset($counts[$v])) {
                        $counts[$v]++;
                    }
                }
            }
            $tallies[] = ['field' => $f, 'counts' => $counts];
        }
        $this->view('forms/responses', ['title' => 'Responses — ' . $form['title'], 'form' => $form, 'fields' => $fields, 'rows' => $rows, 'pager' => $pager, 'answers' => $answers, 'tallies' => $tallies]);
    }

    public function export(int $id): void
    {
        $form = $this->find($id);
        $fields = array_values(array_filter(FormDef::fields($id, true), fn($f) => $f['type'] !== 'section'));
        $rows = db()->all('SELECT * FROM form_responses WHERE form_id = ? ORDER BY id', [$id]);
        $answers = $this->answersFor(array_column($rows, 'id'));
        $out = csv_start('form_' . $id . '_responses.csv');
        $head = ['#', 'Submitted'];
        if ($form['collect_contact']) {
            array_push($head, 'Name', 'Phone');
        }
        csv_row($out, array_merge($head, array_column($fields, 'label')));
        foreach ($rows as $r) {
            $line = [$r['id'], $r['created_at']];
            if ($form['collect_contact']) {
                array_push($line, $r['respondent_name'], $r['respondent_phone']);
            }
            foreach ($fields as $f) {
                $a = $answers[$r['id']][$f['id']] ?? null;
                $line[] = $a ? ($f['type'] === 'file' ? (string) $a['file_name'] : FormDef::display($a['value'], $f['type'])) : '';
            }
            csv_row($out, $line);
        }
        audit('form_export', 'forms', $id);
        csv_end($out);
    }

    public function response(int $id, int $resp): void
    {
        $form = $this->find($id);
        $r = db()->one('SELECT r.*, u.name AS user_name FROM form_responses r LEFT JOIN users u ON u.id = r.user_id WHERE r.id = ? AND r.form_id = ?', [$resp, $id]) ?? $this->notFound();
        $this->view('forms/response', [
            'title' => 'Response #' . $resp, 'form' => $form, 'r' => $r,
            'fields' => FormDef::fields($id, true), 'answers' => $this->answersFor([$resp])[$resp] ?? [],
        ]);
    }

    public function file(int $id, int $resp, int $field): void
    {
        $this->find($id);
        $a = db()->one('SELECT a.* FROM form_answers a JOIN form_responses r ON r.id = a.response_id WHERE r.form_id = ? AND a.response_id = ? AND a.field_id = ? AND a.file_stored IS NOT NULL', [$id, $resp, $field]) ?? $this->notFound();
        Upload::send($a['file_stored'], $a['file_mime'], $a['file_name']);
    }

    // ---------------------------------------------------------------------------------

    private function answersFor(array $responseIds): array
    {
        if (!$responseIds) {
            return [];
        }
        $in = implode(',', array_fill(0, count($responseIds), '?'));
        $out = [];
        foreach (db()->all("SELECT * FROM form_answers WHERE response_id IN ($in)", array_values($responseIds)) as $a) {
            $out[$a['response_id']][$a['field_id']] = $a;
        }
        return $out;
    }

    private function fieldInput(int $formId): array
    {
        $in = $this->only(['label', 'type', 'options', 'help_text']);
        $in['is_required'] = isset($_POST['is_required']) ? 1 : 0;
        $errors = Validator::check($in, [
            'label'     => 'required|max:190',
            'type'      => 'required|in:' . implode(',', array_keys(FormDef::TYPES)),
            'help_text' => 'max:255',
        ]);
        if (!$errors && in_array($in['type'], FormDef::CHOICE_TYPES, true) && count(FormDef::options($in['options'])) < 2) {
            $errors['options'] = 'Add at least two options, one per line.';
        }
        if ($errors) {
            flash('error', reset($errors));
            redirect('forms/' . $formId);
        }
        if (!in_array($in['type'], FormDef::CHOICE_TYPES, true)) {
            $in['options'] = null;
        } else {
            $in['options'] = implode("\n", array_map(fn($o) => mb_substr($o, 0, 120), FormDef::options($in['options'])));
        }
        if ($in['type'] === 'section') {
            $in['is_required'] = 0;
        }
        return $in;
    }

    private function starterFields(string $template): array
    {
        $yes = "Yes, I give permission\nNo";
        switch ($template) {
            case 'consent':
                return [
                    ['label' => 'Student’s full name', 'type' => 'text', 'is_required' => 1],
                    ['label' => 'Class', 'type' => 'text', 'is_required' => 1],
                    ['label' => 'Do you give permission for your child to take part?', 'type' => 'radio', 'options' => $yes, 'is_required' => 1],
                    ['label' => 'Medical conditions or allergies we should know about', 'type' => 'textarea', 'is_required' => 0],
                    ['label' => 'Emergency contact number', 'type' => 'phone', 'is_required' => 1],
                ];
            case 'survey':
                return [
                    ['label' => 'How satisfied are you with the school this term?', 'type' => 'radio', 'options' => "Very satisfied\nSatisfied\nNot sure\nDissatisfied", 'is_required' => 1],
                    ['label' => 'What should the school improve?', 'type' => 'textarea', 'is_required' => 0],
                ];
            case 'rsvp':
                return [
                    ['label' => 'Will you attend?', 'type' => 'radio', 'options' => "Yes\nNo\nMaybe", 'is_required' => 1],
                    ['label' => 'Number of people attending', 'type' => 'number', 'is_required' => 0],
                ];
            default:
                return [];
        }
    }

    private function find(int $id): array
    {
        return db()->one('SELECT * FROM forms WHERE id = ?', [$id]) ?? $this->notFound();
    }

    private function fieldOr404(int $formId, int $fieldId): array
    {
        return db()->one('SELECT * FROM form_fields WHERE id = ? AND form_id = ?', [$fieldId, $formId]) ?? $this->notFound();
    }
}
