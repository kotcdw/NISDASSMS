<?php $docTitle = $plan['kind'] === 'exam' ? 'EXAM SEATING PLAN' : 'SEATING PLAN'; $docSub = $plan['title'] . ($plan['room_name'] ? ' · ' . $plan['room_name'] : ''); ?>
<section class="card-page doc-page print-tables<?= (int) $plan['seat_cols'] > 6 ? ' landscape' : '' ?>">
    <?php require BASE_PATH . '/app/views/fees/_doc_head.php'; ?>
    <p style="text-align:center;font-weight:700;border:1px solid #c8ced8;padding:4px;margin:0 0 6px">FRONT · board and teacher's table</p>
    <table style="table-layout:fixed">
        <?php for ($r = 1; $r <= (int) $plan['seat_rows']; $r++): ?>
        <tr><?php for ($c = 1; $c <= (int) $plan['seat_cols']; $c++): $pid = $seats["$r-$c"] ?? null; $p = $pid ? ($pupils[$pid] ?? null) : null; ?>
            <td style="height:44px;vertical-align:top;font-size:.72rem"><span style="color:#888"><?= $r ?>.<?= $c ?></span><br><?= $p ? '<strong>' . e(Seating::name($p)) . '</strong>' . ($plan['kind'] === 'exam' ? '<br>' . e($p['class_name']) : '') : '' ?></td>
        <?php endfor; ?></tr>
        <?php endfor; ?>
    </table>
    <?php if ($unseated): ?><p style="font-size:.8rem"><strong>Not seated:</strong> <?= e(implode('; ', array_map(fn($p) => Seating::name($p), $unseated))) ?></p><?php endif; ?>
    <?php if ($plan['kind'] === 'exam'): ?>
    <h2>Seat list</h2>
    <table><thead><tr><th>Seat</th><th>Student</th><th>Class</th><th>Admission no.</th></tr></thead><tbody>
        <?php ksort($seats, SORT_NATURAL); foreach ($seats as $k => $pid): $p = $pupils[$pid] ?? null; if (!$p) continue; ?>
            <tr><td><?= e(str_replace('-', '.', $k)) ?></td><td><?= e(Seating::name($p)) ?></td><td><?= e($p['class_name']) ?></td><td><?= e($p['admission_no']) ?></td></tr>
        <?php endforeach; ?>
    </tbody></table>
    <?php endif; ?>
</section>
