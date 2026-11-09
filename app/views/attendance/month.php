<?php $docTitle = 'ATTENDANCE REGISTER'; $docSub = $class['name'] . ' · ' . date('F Y', strtotime($m . '-01')); $sym = ['present' => 'P', 'absent' => 'A', 'late' => 'L', 'excused' => 'E', 'sick' => 'S']; ?>
<div class="toolbar no-print" style="position:static;background:#eef1f6;color:#1d2433">
    <a href="<?= e(url('attendance/class/' . $class['id'] . '/month', ['m' => date('Y-m', strtotime($m . '-01 -1 month'))])) ?>">← <?= e(date('M Y', strtotime($m . '-01 -1 month'))) ?></a>
    <a href="<?= e(url('attendance/class/' . $class['id'] . '/month', ['m' => date('Y-m', strtotime($m . '-01 +1 month'))])) ?>"><?= e(date('M Y', strtotime($m . '-01 +1 month'))) ?> →</a>
</div>
<div class="card-page doc-page landscape">
    <?php require dirname(__DIR__) . '/fees/_doc_head.php'; ?>
    <p style="font-size:.8rem">Class teacher: <?= e($class['teacher'] ?? '—') ?> · P present · L late · A absent · E excused · S sick · grey = no register taken</p>
    <table class="lines reg-grid" style="width:100%">
        <tr><th style="text-align:left">Student</th><?php foreach ($days as $d): ?><th><?= date('j', strtotime($d)) ?><br><small><?= substr(date('D', strtotime($d)), 0, 2) ?></small></th><?php endforeach; ?><th>P</th><th>A</th><th>%</th></tr>
        <?php foreach ($pupils as $p): $pp = 0; $aa = 0; $nn = 0; ?>
        <tr><td style="white-space:nowrap"><?= e($p['last_name'] . ' ' . $p['first_name']) ?></td>
            <?php foreach ($days as $d): $s = $marks[$p['id']][$d] ?? null; if ($s) { $nn++; if (in_array($s, ['present', 'late'], true)) $pp++; if ($s === 'absent') $aa++; } ?>
                <td class="<?= isset($taken[$d]) ? ($s === 'absent' ? 'abs' : '') : 'none' ?>"><?= $s ? $sym[$s] : '' ?></td>
            <?php endforeach; ?>
            <td><?= $pp ?></td><td><?= $aa ?></td><td><?= $nn ? round($pp * 100 / $nn) : '' ?></td></tr>
        <?php endforeach; ?>
    </table>
    <div class="rc-sign"><div><span></span>Class teacher</div><div><span></span>Head Teacher</div></div>
</div>
