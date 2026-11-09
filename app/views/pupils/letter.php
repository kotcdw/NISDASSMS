<?php
$name = trim($p['first_name'] . ' ' . ($p['middle_name'] ?? '') . ' ' . $p['last_name']);
$he = $p['gender'] === 'male' ? 'he' : 'she'; $his = $p['gender'] === 'male' ? 'his' : 'her'; $him = $p['gender'] === 'male' ? 'him' : 'her';
$since = $p['first_enrolled'] ?: $p['admitted_on'];
$docTitle = strtoupper($title); $docSub = date('j F Y');
?>
<div class="card-page doc-page letter">
    <?php require dirname(__DIR__) . '/fees/_doc_head.php'; ?>
    <p style="margin-top:1.5rem">Our ref: <?= e($p['admission_no']) ?><br>Date: <?= e(date('j F Y')) ?></p>
    <p><?= $kind === 'transfer' ? 'The Head Teacher,<br>…………………………………………<br>…………………………………………' : 'To whom it may concern' ?></p>
    <p><strong><?= e(strtoupper($kind === 'confirmation' ? 'Confirmation of studentship' : ($kind === 'transfer' ? 'Transfer of student' : 'Testimonial'))) ?>: <?= e(strtoupper($name)) ?></strong></p>
    <?php if ($kind === 'confirmation'): ?>
        <p>This is to confirm that <strong><?= e($name) ?></strong> (admission number <?= e($p['admission_no']) ?>), born on <?= e(date('j F Y', strtotime($p['date_of_birth']))) ?>, is a student of <?= e(setting('school_name')) ?> and is currently in <strong><?= e($p['class_name'] ?? '—') ?></strong> for the <?= e(current_year()['name'] ?? '') ?> academic year. <?= ucfirst($he) ?> has been in the school since <?= e(date('F Y', strtotime($since))) ?>.</p>
        <?php if ($att && (int) $att['n']): ?><p><?= ucfirst($his) ?> attendance this term is <?= round($att['p'] * 100 / $att['n']) ?>% (<?= (int) $att['p'] ?> of <?= (int) $att['n'] ?> days).</p><?php endif; ?>
        <p>This letter is issued at the request of the parent/guardian for whatever purpose it may serve.</p>
    <?php elseif ($kind === 'transfer'): ?>
        <p>This is to introduce <strong><?= e($name) ?></strong>, born on <?= e(date('j F Y', strtotime($p['date_of_birth']))) ?>, who has been a student of <?= e(setting('school_name')) ?> since <?= e(date('F Y', strtotime($since))) ?> and was last in <strong><?= e($p['class_name'] ?? '—') ?></strong>.</p>
        <p>The parent/guardian has requested a transfer, and we have no objection to <?= $him ?> continuing <?= $his ?> education in your school. <?= ucfirst($his) ?> report cards and records are available on request.</p>
        <?php if ($conduct !== ''): ?><p>Conduct: <?= e($conduct) ?></p><?php endif; ?>
        <?php if ($balance !== null && $balance > 0.004): ?><p class="no-print" style="font-size:.85rem;color:#b42318">Not printed — for the school office: an amount of <?= e(Fees::money($balance)) ?> was outstanding on the student's account on the date of this letter.</p><?php endif; ?>
        <p>We wish <?= $him ?> every success.</p>
    <?php else: ?>
        <p>This is to certify that <strong><?= e($name) ?></strong>, born on <?= e(date('j F Y', strtotime($p['date_of_birth']))) ?>, was a student of <?= e(setting('school_name')) ?> from <?= e(date('F Y', strtotime($since))) ?><?= $p['left_on'] ? ' to ' . e(date('F Y', strtotime($p['left_on']))) : '' ?>, last in <?= e($p['class_name'] ?? '—') ?>.</p>
        <p><?= $conduct !== '' ? 'Conduct and character: ' . e($conduct) : 'During ' . $his . ' time with us ' . $he . ' was of good conduct and character.' ?></p>
        <p>We recommend <?= $him ?> to any institution or person who may require <?= $his ?> services, and wish <?= $him ?> God's richest blessings.</p>
    <?php endif; ?>
    <div style="margin-top:3rem">………………………………………<br><strong><?= e(db()->value("SELECT name FROM users WHERE role = 'head_teacher' AND is_active = 1 ORDER BY id LIMIT 1") ?: 'Head Teacher') ?></strong><br>Head Teacher</div>
    <p style="margin-top:2rem;font-size:.75rem;color:#777">Not valid without the school stamp.</p>
</div>
