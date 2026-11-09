<?php
declare(strict_types=1);

/** Quizzes: who may set them, marking, and attempt rules. */
final class Quiz
{
    public const TYPES = ['mcq' => 'Multiple choice', 'truefalse' => 'True / false', 'short' => 'Short answer (word or number)'];
    public const SHOW = ['after_close' => 'After the quiz closes', 'after_submit' => 'Straight after submitting', 'never' => 'Never (score only)'];
    public const GRACE_SECONDS = 60;

    /** Class subjects the signed-in teacher may set quizzes for. */
    public static function classSubjects(): array
    {
        $sql = 'SELECT cs.id, cs.class_id, c.name AS class_name, s.name AS subject_name FROM class_subjects cs
                JOIN classes c ON c.id = cs.class_id JOIN subjects s ON s.id = cs.subject_id JOIN levels l ON l.id = c.level_id
                WHERE c.is_active = 1';
        $p = [];
        if (!can('exams.manage')) {
            $sql .= ' AND (cs.teacher_id = ? OR c.class_teacher_id = ?)';
            $p = [Auth::id(), Auth::id()];
        }
        return db()->all($sql . ' ORDER BY l.sort_order, c.name, s.name', $p);
    }

    public static function canManage(array $quiz): bool
    {
        if (can('exams.manage')) {
            return true;
        }
        return (bool) db()->value(
            'SELECT 1 FROM class_subjects cs JOIN classes c ON c.id = cs.class_id WHERE cs.id = ? AND (cs.teacher_id = ? OR c.class_teacher_id = ?)',
            [$quiz['class_subject_id'], Auth::id(), Auth::id()]
        ) || (int) $quiz['created_by'] === Auth::id();
    }

    public static function isOpen(array $q, ?string $now = null): bool
    {
        $now = $now ?: date('Y-m-d H:i:s');
        return $q['status'] === 'published' && (!$q['opens_at'] || $q['opens_at'] <= $now) && (!$q['closes_at'] || $q['closes_at'] >= $now);
    }

    public static function options(array $question): array
    {
        $o = json_decode((string) $question['options'], true);
        return is_array($o) ? array_values($o) : [];
    }

    /** Normalise a short answer for comparison: lower case, single spaces, no trailing full stop. */
    public static function norm(string $s): string
    {
        $s = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $s)));
        return rtrim($s, '.');
    }

    public static function isCorrect(array $q, $given): bool
    {
        if ($given === null || $given === '') {
            return false;
        }
        switch ($q['type']) {
            case 'mcq':
                return (string) (int) $given === (string) $given && (int) $given === (int) $q['answer'];
            case 'truefalse':
                return $given === $q['answer'];
            default:
                $g = self::norm((string) $given);
                foreach (explode('|', (string) $q['answer']) as $acc) {
                    $acc = self::norm($acc);
                    if ($acc === '') {
                        continue;
                    }
                    // numbers: 2.50 equals 2.5
                    if (is_numeric($acc) && is_numeric(str_replace(',', '', $g)) && abs((float) $acc - (float) str_replace(',', '', $g)) < 1e-9) {
                        return true;
                    }
                    if ($g === $acc) {
                        return true;
                    }
                }
                return false;
        }
    }

    /** @return array{0: float, 1: float} score, maximum */
    public static function mark(array $questions, array $answers): array
    {
        $score = 0.0;
        $max = 0.0;
        foreach ($questions as $q) {
            $max += (float) $q['marks'];
            if (self::isCorrect($q, $answers[$q['id']] ?? null)) {
                $score += (float) $q['marks'];
            }
        }
        return [round($score, 2), round($max, 2)];
    }

    /** Mark and close an attempt (used on submit, on timeout and when the answer key changes). */
    public static function finalise(int $attemptId, ?array $answers = null): void
    {
        $a = db()->one('SELECT * FROM quiz_attempts WHERE id = ?', [$attemptId]);
        if (!$a) {
            return;
        }
        $answers = $answers ?? (json_decode((string) $a['answers'], true) ?: []);
        $questions = db()->all('SELECT * FROM quiz_questions WHERE quiz_id = ?', [$a['quiz_id']]);
        [$score, $max] = self::mark($questions, $answers);
        db()->update('quiz_attempts', [
            'answers' => json_encode($answers, JSON_UNESCAPED_UNICODE), 'score' => $score, 'max_score' => $max,
            'submitted_at' => $a['submitted_at'] ?: date('Y-m-d H:i:s'),
        ], 'id = ?', [$attemptId]);
    }

    /** Re-mark every submitted attempt after the teacher corrects a question. */
    public static function remark(int $quizId): int
    {
        $n = 0;
        foreach (db()->all('SELECT id FROM quiz_attempts WHERE quiz_id = ? AND submitted_at IS NOT NULL', [$quizId]) as $a) {
            self::finalise((int) $a['id']);
            $n++;
        }
        return $n;
    }

    /** Close attempts whose time ran out (pupil closed the browser). */
    public static function expireStale(?int $quizId = null): void
    {
        $sql = 'SELECT id FROM quiz_attempts WHERE submitted_at IS NULL AND deadline_at IS NOT NULL AND deadline_at < (NOW() - INTERVAL ' . self::GRACE_SECONDS . ' SECOND)';
        $p = [];
        if ($quizId) {
            $sql .= ' AND quiz_id = ?';
            $p[] = $quizId;
        }
        foreach (db()->all($sql, $p) as $a) {
            self::finalise((int) $a['id']);
        }
    }

    public static function showAnswers(array $quiz): bool
    {
        return $quiz['show_answers'] === 'after_submit'
            || ($quiz['show_answers'] === 'after_close' && ($quiz['status'] === 'closed' || ($quiz['closes_at'] && $quiz['closes_at'] < date('Y-m-d H:i:s'))));
    }

    public static function fmt($v): string
    {
        return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    }
}
