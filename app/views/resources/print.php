<?php $docTitle = 'CLASS RESOURCES'; $docSub = 'Shortages and repairs · ' . date('j M Y'); ?>
<section class="card-page doc-page print-tables">
    <?php require BASE_PATH . '/app/views/fees/_doc_head.php'; ?>
    <h2>Shortages (what is needed for the number of students)</h2>
    <table>
        <thead><tr><th>Room</th><th>Item</th><th class="rpt-num">Students</th><th class="rpt-num">Needed</th><th class="rpt-num">Good</th><th class="rpt-num">Short</th></tr></thead>
        <tbody>
        <?php foreach ($shortages as $s): ?><tr><td><?= e($s['room_name']) ?></td><td><?= e($s['name']) ?></td><td class="rpt-num"><?= $s['class_id'] ? (int) $s['roll'] : '' ?></td><td class="rpt-num"><?= (int) $s['need'] ?></td><td class="rpt-num"><?= (int) $s['qty_good'] ?></td><td class="rpt-num"><strong><?= (int) $s['short'] ?></strong></td></tr><?php endforeach; ?>
        <?php if (!$shortages): ?><tr><td colspan="6">No shortages recorded.</td></tr><?php endif; ?>
        </tbody>
        <?php if ($shortages): ?><tfoot><tr><th colspan="5">Total pieces short</th><th class="rpt-num"><?= array_sum(array_column($shortages, 'short')) ?></th></tr></tfoot><?php endif; ?>
    </table>
    <h2>Needs repair or broken</h2>
    <table>
        <thead><tr><th>Room</th><th>Item</th><th class="rpt-num">Needs repair</th><th class="rpt-num">Broken</th><th>Notes</th></tr></thead>
        <tbody>
        <?php foreach ($repairs as $s): ?><tr><td><?= e($s['room_name']) ?></td><td><?= e($s['name']) ?></td><td class="rpt-num"><?= (int) $s['qty_repair'] ?></td><td class="rpt-num"><?= (int) $s['qty_bad'] ?></td><td><?= e((string) $s['notes']) ?></td></tr><?php endforeach; ?>
        <?php if (!$repairs): ?><tr><td colspan="5">Nothing recorded as needing repair.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <div class="rc-sign"><div><span></span>Prepared by</div><div><span></span>Head of school</div><div><span></span>Date</div></div>
</section>
