<?php $hm = fn($t) => substr((string) $t, 0, 5); ?>
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('timetable')) ?>">Time table</a> /</div>
    <h1><?= e($teacher['name']) ?></h1></div>
    <div class="actions"><button class="btn btn-ghost" type="button" data-print>Print</button></div>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<?php if (!$total): ?>
    <div class="alert alert-info">No lessons on the time table yet.</div>
<?php else: ?>
<div class="tt-week">
    <?php foreach (Timetable::DAYS as $d => $dn): ?>
    <div class="card" style="margin:0">
        <h3><?= e($dn) ?> <small><?= count($byDay[$d] ?? []) ?> lessons</small></h3>
        <?php foreach ($byDay[$d] ?? [] as $l): ?>
            <div class="tt-item">
                <span class="tt-time"><?= e($hm($l['start_time'])) ?>–<?= e($hm($l['end_time'])) ?></span>
                <strong><?= e($l['subject_name'] ?? '') ?></strong><br>
                <small><a href="<?= e(url('timetable/class/' . $l['class_id'])) ?>"><?= e($l['class_name']) ?></a><?= $l['room'] ? ' · ' . e($l['room']) : '' ?></small>
            </div>
        <?php endforeach; ?>
        <?php if (empty($byDay[$d])): ?><p class="muted" style="font-size:.85rem">Free</p><?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
