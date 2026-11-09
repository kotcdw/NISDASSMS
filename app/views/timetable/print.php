<?php $hm = fn($t) => substr((string) $t, 0, 5); ?>
<?php foreach ($data as $item): $class = $item['class']; ?>
<section class="card-page tt-page">
    <header class="rc-head">
        <img class="rc-logo-img" src="<?= e(logo_url()) ?>" alt="">
        <div class="rc-school"><h1><?= e(school_name()) ?></h1><p>Class time table</p></div>
        <div class="rc-title"><strong><?= e($class['name']) ?></strong><span>Class teacher: <?= e($class['teacher_name'] ?? '—') ?></span></div>
    </header>
    <table class="tt-print">
        <tr><th>Time</th><?php foreach (Timetable::DAYS as $dn): ?><th><?= e($dn) ?></th><?php endforeach; ?></tr>
        <?php foreach ($item['periods'] as $p): ?>
            <?php if ($p['kind'] !== 'lesson'): ?>
                <tr class="fixed"><td><?= e($hm($p['start_time'])) ?>–<?= e($hm($p['end_time'])) ?></td><td colspan="5"><?= e($p['label']) ?></td></tr>
            <?php else: ?>
                <tr>
                    <td><strong><?= e($p['label']) ?></strong><br><small><?= e($hm($p['start_time'])) ?>–<?= e($hm($p['end_time'])) ?></small></td>
                    <?php foreach (Timetable::DAYS as $d => $dn): $s = $item['grid'][$d][$p['id']] ?? null; ?>
                        <td><?php if ($s && $s['class_subject_id']): ?><strong><?= e($s['subject_name']) ?></strong><br><small><?= e($s['teacher_name'] ?? '') ?><?= $s['room'] ? ' · ' . e($s['room']) : '' ?></small><?php endif; ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endif; ?>
        <?php endforeach; ?>
    </table>
</section>
<?php endforeach; ?>
<?php if (!$data): ?><p style="padding:2rem">No classes.</p><?php endif; ?>
