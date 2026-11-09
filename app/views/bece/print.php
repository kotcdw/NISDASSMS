<?php $docTitle = 'BECE CANDIDATES'; $docSub = $year['name']; ?>
<div class="card-page doc-page">
    <?php require dirname(__DIR__) . '/fees/_doc_head.php'; ?>
    <table class="lines" style="width:100%">
        <tr><th>#</th><th style="text-align:left">Surname</th><th style="text-align:left">Other names</th><th>Sex</th><th>Date of birth</th><th style="text-align:left">Ghana Card no.</th><th style="text-align:left">Index no.</th><th>Photo</th></tr>
        <?php foreach ($rows as $i => $r): ?>
        <tr><td><?= $i + 1 ?></td><td><?= e(strtoupper($r['last_name'])) ?></td><td><?= e(strtoupper(trim($r['first_name'] . ' ' . ($r['middle_name'] ?? '')))) ?></td><td style="text-align:center"><?= $r['gender'] === 'male' ? 'M' : 'F' ?></td>
            <td style="text-align:center"><?= e(date('d/m/Y', strtotime($r['date_of_birth']))) ?></td><td><?= e($r['nia_no'] ?? '') ?></td><td><?= e($r['index_no'] ?? '') ?></td><td style="text-align:center"><?= $r['photo_path'] ? '✓' : '✗' ?></td></tr>
        <?php endforeach; ?>
    </table>
    <p style="font-size:.8rem;color:#555">Candidates and parents: check spelling of names and date of birth carefully — they appear on the BECE certificate.</p>
    <div class="rc-sign"><div><span></span>Form Master (JHS 3)</div><div><span></span>Head Teacher</div></div>
</div>
