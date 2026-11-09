<?php
$exam = $plan['kind'] === 'exam';
$seatLabel = fn(int $r, int $c) => 'Row ' . $r . ', seat ' . $c;
$tag = array_flip($classNames);
$pupilBtn = function (array $p) use ($exam, $tag): string {
    return '<button type="button" class="seat-pupil' . ($p['gender'] === 'male' ? ' is-boy' : ' is-girl') . ($exam ? ' cls-' . ((int) ($tag[$p['class_name']] ?? 0) % 6) : '') . '" draggable="true" data-pid="' . (int) $p['id'] . '" title="' . e(Seating::name($p) . ' · ' . $p['class_name']) . '">'
        . e($p['first_name'] . ' ' . mb_substr($p['last_name'], 0, 1) . '.') . ($exam ? '<small>' . e($p['class_name']) . '</small>' : '') . '</button>';
};
$back = $exam ? ($plan['series_id'] ? url('exam-timetable/' . $plan['series_id']) : url('seating')) : url('seating');
?>
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e($back) ?>"><?= $exam && $plan['series_name'] ? e($plan['series_name']) : 'Seating plans' ?></a> /</div>
    <h1><?= e($plan['title']) ?></h1></div>
    <div class="actions"><a class="btn btn-ghost" href="<?= e(url('seating/' . $plan['id'] . '/print')) ?>" target="_blank" rel="noopener">Print</a></div>
</div>

<?php if ($unseated): ?><div class="alert alert-warning"><?= count($unseated) ?> student(s) have no seat. Drag them into an empty seat, or add rows or seats below and fill the plan again.</div><?php endif; ?>

<form method="post" action="<?= e(url('seating/' . $plan['id'] . '/save')) ?>" class="seat-editor" data-seating>
    <?= csrf_field() ?>
    <div class="seat-front">FRONT · board and teacher's table</div>
    <div class="seat-grid" style="grid-template-columns:repeat(<?= (int) $plan['seat_cols'] ?>, minmax(88px, 1fr))">
        <?php for ($r = 1; $r <= (int) $plan['seat_rows']; $r++): for ($c = 1; $c <= (int) $plan['seat_cols']; $c++): $pid = $seats["$r-$c"] ?? null; ?>
            <div class="seat" data-seat="<?= $r ?>-<?= $c ?>" aria-label="<?= e($seatLabel($r, $c)) ?>">
                <span class="seat-no"><?= $r ?>.<?= $c ?></span>
                <input type="hidden" name="seat[<?= $r ?>][<?= $c ?>]" value="<?= $pid ? (int) $pid : '' ?>">
                <?= $pid && isset($pupils[$pid]) ? $pupilBtn($pupils[$pid]) : '' ?>
            </div>
        <?php endfor; endfor; ?>
    </div>
    <h3 style="font-size:.95rem;margin:1rem 0 .3rem">Not seated</h3>
    <div class="seat-pool" data-pool><?php foreach ($unseated as $p): ?><?= $pupilBtn($p) ?><?php endforeach; ?><?php if (!$unseated): ?><span class="muted seat-pool-empty" style="font-size:.85rem">Everyone has a seat. Drag a student here to take them out of the plan.</span><?php endif; ?></div>
    <button class="btn btn-primary" type="submit">Save seating plan</button> <span class="seat-dirty muted" hidden>Unsaved changes</span>
</form>

<div class="grid-2" style="margin-top:1.25rem">
    <form method="post" action="<?= e(url('seating/' . $plan['id'] . '/rearrange')) ?>" class="form card" data-confirm="Fill the plan again? Moves you made by hand will be replaced.">
        <?= csrf_field() ?>
        <h2>Change the layout / fill again</h2>
        <?php $rows = (int) $plan['seat_rows']; $cols = (int) $plan['seat_cols']; $method = $plan['method']; require __DIR__ . '/_grid_form.php'; ?>
        <button class="btn" type="submit">Fill the plan again</button>
    </form>
    <div>
        <form method="post" action="<?= e(url('seating/' . $plan['id'] . '/swap')) ?>" class="form card">
            <?= csrf_field() ?>
            <h2>Swap two seats</h2>
            <?php $opts = ''; for ($r = 1; $r <= (int) $plan['seat_rows']; $r++) { for ($c = 1; $c <= (int) $plan['seat_cols']; $c++) { $pid = $seats["$r-$c"] ?? null; $opts .= '<option value="' . $r . '-' . $c . '">' . e($r . '.' . $c . ' — ' . ($pid && isset($pupils[$pid]) ? Seating::name($pupils[$pid]) : 'empty')) . '</option>'; } } ?>
            <div class="grid-2"><label>Seat<select name="a"><?= $opts ?></select></label><label>With seat<select name="b"><?= $opts ?></select></label></div>
            <button class="btn" type="submit">Swap</button>
        </form>
        <form method="post" action="<?= e(url('seating/' . $plan['id'] . '/delete')) ?>" data-confirm="Delete this seating plan?"><?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit">Delete plan</button></form>
    </div>
</div>
