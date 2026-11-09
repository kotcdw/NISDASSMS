<?php $week = (int) $n['week_no']; $docTitle = 'LESSON NOTE'; $docSub = $n['class_name'] . ' · ' . $n['subject_name'] . ' · Week ' . $week . ' (' . LessonNotes::weekRange($term, $week) . ') · ' . $n['year_name'] . ' ' . $n['term_name']; ?>
<section class="card-page doc-page print-tables">
    <?php require BASE_PATH . '/app/views/fees/_doc_head.php'; ?>
    <table>
        <tr><th style="width:26%">Teacher</th><td><?= e($n['teacher_name'] ?? '') ?></td></tr>
        <tr><th>Status</th><td><?= e(LessonNotes::STATUS[$n['status']][0]) ?><?= $n['reviewer_name'] ? ' — ' . e($n['reviewer_name']) . ($n['reviewed_at'] ? ', ' . e(fmt_date($n['reviewed_at'], 'd M Y')) : '') : '' ?></td></tr>
        <?php foreach (['strand_name' => 'Strand / sub-strand', 'content_standard' => 'Content standard', 'topic' => 'Topic', 'indicators' => 'Indicator(s)', 'core_competencies' => 'Core competencies', 'resources' => 'T/L resources',
            'starter' => 'Phase 1: Starter', 'main_activities' => 'Phase 2: Main activities', 'plenary' => 'Phase 3: Plenary / reflection', 'assessment' => 'Assessment', 'references_text' => 'References', 'reflection' => 'Reflection', 'review_comment' => 'Vetting comment'] as $k => $l): if (trim((string) ($n[$k] ?? '')) === '') continue; ?>
        <tr><th style="vertical-align:top"><?= e($l) ?></th><td><?= nl2br(e((string) $n[$k])) ?></td></tr>
        <?php endforeach; ?>
    </table>
    <p style="margin-top:2.5rem;display:flex;justify-content:space-between;font-size:.85rem"><span>Teacher's signature: ____________________</span><span>Head teacher's signature: ____________________</span></p>
    <p class="no-print" style="text-align:center"><button class="btn" type="button" data-print>Print</button></p>
</section>
