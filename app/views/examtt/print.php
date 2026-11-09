<?php $hm = fn($t) => substr((string) $t, 0, 5); ?>
<?php foreach ($groups as $name => $list): $docTitle = mb_strtoupper($s['name']); $docSub = ($mode === 'invigilator' ? 'Invigilation duties: ' : '') . $name; ?>
<section class="card-page doc-page print-tables">
    <?php require BASE_PATH . '/app/views/fees/_doc_head.php'; ?>
    <p style="font-size:.85rem"><?= e($s['year_name'] . ' · ' . $s['term_name']) ?> · <?= e(date('j M', strtotime($s['start_date']))) ?> – <?= e(date('j M Y', strtotime($s['end_date']))) ?></p>
    <?php if ($s['instructions'] && $mode !== 'invigilator'): ?><p class="doc-note" style="white-space:pre-line"><?= e($s['instructions']) ?></p><?php endif; ?>
    <table>
        <thead><tr><th>Day</th><th>Time</th><?= $mode !== 'class' ? '<th>Class</th>' : '' ?><th>Subject / paper</th><th>Room</th><?= $mode !== 'invigilator' ? '<th>Invigilator</th>' : '' ?></tr></thead>
        <tbody><?php $prev = null; foreach ($list as $p): ?>
            <tr><td><?= $p['exam_date'] !== $prev ? '<strong>' . e(date('D j M', strtotime($p['exam_date']))) . '</strong>' : '' ?></td>
                <td><?= e($hm($p['start_time'])) ?>–<?= e($hm($p['end_time'])) ?></td>
                <?= $mode !== 'class' ? '<td>' . e($p['class_name']) . '</td>' : '' ?>
                <td><?= e($p['subject_name']) ?><?= $p['paper'] ? ' — ' . e($p['paper']) : '' ?><?= $p['notes'] ? '<br><small>' . e($p['notes']) . '</small>' : '' ?></td>
                <td><?= e($p['room_name'] ?? '') ?></td>
                <?= $mode !== 'invigilator' ? '<td>' . e($p['invigilator_name'] ?? '') . '</td>' : '' ?></tr>
        <?php $prev = $p['exam_date']; endforeach; ?></tbody>
    </table>
    <?php if ($mode === 'invigilator'): ?><p style="font-size:.8rem">Collect the question papers from the exams office 15 minutes before each paper and return the scripts, counted and signed, straight after.</p><?php endif; ?>
</section>
<?php endforeach; ?>
<?php if (!$groups): ?><p style="padding:2rem">No papers yet.</p><?php endif; ?>
