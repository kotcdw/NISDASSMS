<?php
declare(strict_types=1);

final class Admission
{
    public const STAGES = [
        'submitted' => ['Submitted', 'info'],
        'screening' => ['Screening / test', 'warn'],
        'interview' => ['Interview', 'warn'],
        'offered'   => ['Offered', 'info'],
        'accepted'  => ['Accepted', 'good'],
        'enrolled'  => ['Enrolled', 'good'],
        'rejected'  => ['Not admitted', 'bad'],
        'withdrawn' => ['Withdrawn', 'neutral'],
    ];

    /** Pipeline order shown on the progress bar. */
    public const FLOW = ['submitted', 'screening', 'interview', 'offered', 'accepted', 'enrolled'];

    /** Allowed manual moves; "enrolled" only happens through the Enrol action. */
    public const TRANSITIONS = [
        'submitted' => ['screening', 'interview', 'offered', 'rejected', 'withdrawn'],
        'screening' => ['interview', 'offered', 'rejected', 'withdrawn'],
        'interview' => ['offered', 'rejected', 'withdrawn'],
        'offered'   => ['accepted', 'rejected', 'withdrawn'],
        'accepted'  => ['withdrawn'],
        'rejected'  => ['screening'],
        'withdrawn' => ['submitted'],
        'enrolled'  => [],
    ];

    public const DOC_TYPES = [
        'birth_certificate' => 'Birth certificate',
        'passport_photo'    => 'Passport photo',
        'previous_report'   => 'Previous school report',
        'immunisation_card' => 'Immunisation / weighing card',
        'transfer_letter'   => 'Transfer letter',
        'other'             => 'Other',
    ];

    public const RELATIONSHIPS = ['Father', 'Mother', 'Guardian', 'Grandparent', 'Uncle', 'Aunt', 'Sibling', 'Other'];

    public static function badge(string $stage): string
    {
        [$label, $tone] = self::STAGES[$stage] ?? [$stage, 'neutral'];
        return badge($label, $tone);
    }

    public static function label(string $stage): string
    {
        return self::STAGES[$stage][0] ?? $stage;
    }

    /** Next reference number like APP-2026-0007 (concurrency-safe). */
    public static function nextRef(Database $d): string
    {
        return 'APP-' . date('Y') . '-' . str_pad((string) self::nextSeq($d, 'application_' . date('Y')), 4, '0', STR_PAD_LEFT);
    }

    /** Next admission number like NIS/2026/0123 (concurrency-safe). */
    public static function nextAdmissionNo(Database $d, string $yearLabel): string
    {
        $prefix = preg_replace('/[^A-Z0-9]/', '', strtoupper(setting('admission_prefix', 'NIS'))) ?: 'NIS';
        // Skip numbers already taken (e.g. pupils imported from Excel with their old admission numbers)
        do {
            $n = self::nextSeq($d, 'admission_' . $yearLabel);
            $no = $prefix . '/' . $yearLabel . '/' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
        } while ($d->value('SELECT id FROM pupils WHERE admission_no = ?', [$no]));
        return $no;
    }

    /** Must be called inside a transaction. */
    private static function nextSeq(Database $d, string $name): int
    {
        $d->run('INSERT IGNORE INTO number_sequences (name, next_value) VALUES (?, 1)', [$name]);
        $n = (int) $d->value('SELECT next_value FROM number_sequences WHERE name = ? FOR UPDATE', [$name]);
        $d->run('UPDATE number_sequences SET next_value = next_value + 1 WHERE name = ?', [$name]);
        return $n;
    }

    /** SMS the applicant's guardian using a template (respects the "admission SMS" setting). */
    public static function notifyGuardian(string $template, array $app, array $extra = []): void
    {
        if (setting('notify_admission', '1') !== '1') {
            return;
        }
        $levelName = $app['level_name'] ?? (string) db()->value('SELECT name FROM levels WHERE id = ?', [$app['level_id']]);
        Notifier::smsTemplate($template, (string) $app['guardian_phone'], $extra + [
            'guardian' => $app['guardian_name'],
            'pupil'    => trim($app['first_name'] . ' ' . $app['last_name']),
            'level'    => $levelName,
            'ref'      => $app['ref_no'],
            'interview_date' => !empty($app['interview_date']) ? date('D j M Y', strtotime($app['interview_date'])) : 'a date to be confirmed',
        ], ['name' => $app['guardian_name'], 'entity' => 'applications', 'entity_id' => (int) $app['id']]);
    }

    public static function normalisePhone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }
        $p = preg_replace('/[^\d+]/', '', $phone);
        if (preg_match('/^\+?233(\d{9})$/', $p, $m)) {
            return '0' . $m[1];
        }
        return $p;
    }

    /** Common application field rules (shared by public form and staff form). */
    public static function rules(): array
    {
        return [
            'level_id'              => 'required|int',
            'first_name'            => 'required|max:80',
            'middle_name'           => 'max:80',
            'last_name'             => 'required|max:80',
            'gender'                => 'required|in:male,female',
            'date_of_birth'         => 'required|date|past',
            'nationality'           => 'max:60',
            'hometown'              => 'max:80',
            'denomination'          => 'max:80',
            'previous_school'       => 'max:150',
            'allergies'             => 'max:255',
            'medical_notes'         => 'max:500',
            'sen_category'          => 'in:' . implode(',', array_keys(self::SEN)),
            'sen_support'           => 'max:500',
            'guardian_name'         => 'required|max:150',
            'guardian_relationship' => 'required|max:40',
            'guardian_phone'        => 'required|phone',
            'guardian_phone_alt'    => 'phone',
            'guardian_email'        => 'email|max:190',
            'guardian_occupation'   => 'max:100',
            'guardian_address'      => 'max:255',
        ];
    }

    /** Special educational needs categories (as used in the GES/EMIS school census). */
    public const SEN = [
        'visual'       => 'Visual impairment (blind / low vision)',
        'hearing'      => 'Hearing impairment (deaf / hard of hearing)',
        'physical'     => 'Physical disability',
        'intellectual' => 'Intellectual disability',
        'speech'       => 'Speech and language difficulty',
        'learning'     => 'Specific learning difficulty (e.g. dyslexia)',
        'autism'       => 'Autism spectrum',
        'behaviour'    => 'Emotional / behavioural difficulty',
        'multiple'     => 'Multiple disabilities',
        'other'        => 'Other',
    ];

    public const FIELDS = [
        'level_id', 'first_name', 'middle_name', 'last_name', 'gender', 'date_of_birth', 'nationality', 'hometown',
        'denomination', 'previous_school', 'medical_notes', 'allergies', 'sen_category', 'sen_support', 'guardian_name', 'guardian_relationship',
        'guardian_phone', 'guardian_phone_alt', 'guardian_email', 'guardian_occupation', 'guardian_address',
    ];
}
