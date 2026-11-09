<?php $docTitle = 'SCHEME OF WORK'; $docSub = $cs['class_name'] . ' · ' . $cs['subject_name'] . ' · ' . $term['year_name'] . ' ' . $term['name']; ?>
<section class="card-page doc-page print-tables">
    <?php require BASE_PATH . '/app/views/fees/_doc_head.php'; ?>
    <p style="font-size:.8rem">Teacher: <strong><?= e($cs['teacher_name'] ?? '—') ?></strong> · Topics taught: <strong><?= (int) $cov['taught'] ?> of <?= (int) $cov['planned'] ?></strong><?= $cov['pct'] !== null ? ' (' . e(Results::fmt($cov['pct'])) . '%)' : '' ?></p>
    <table>
        <thead><tr><th style="width:6%">Week</th><th style="width:20%">Strand / sub-strand</th><th>Topic</th><th>Indicators</th><th style="width:16%">Resources</th><th style="width:10%">Taught</th><th>Remark</th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
            <tr><td><?= (int) $it['week_no'] ?></td><td><?= e(trim(($it['parent_strand_name'] ? $it['parent_strand_name'] . ' › ' : '') . ($it['strand_name'] ?? ''))) ?></td><td><?= e($it['topic']) ?></td><td><?= e((string) $it['indicators']) ?></td><td><?= e((string) $it['resources']) ?></td><td><?= $it['taught_on'] ? e(date('d/m/Y', strtotime($it['taught_on']))) : '' ?></td><td><?= e((string) $it['remark']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="7">No topics planned.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <div class="rc-sign"><div><span></span>Teacher</div><div><span></span>Head of school (vetted)</div><div><span></span>Date</div></div>
</section>
