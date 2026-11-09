<div class="page-head">
    <div><p class="breadcrumb"><a href="<?= e(url('portal')) ?>">My children</a></p><h1><?= e(full_name($child)) ?></h1>
    <p class="muted"><?= e($child['admission_no']) ?> · <?= e($child['class_name'] ?? '') ?><?= $child['class_teacher'] ? ' · Class teacher: ' . e($child['class_teacher']) : '' ?></p></div>
    <div class="actions"><a class="btn btn-primary" href="<?= e(url('conversations/create', ['pupil' => $child['id']])) ?>">Write to a teacher</a></div>
</div>
<?php require __DIR__ . '/_child_stats.php'; ?>
<div class="cols" style="margin-top:1.25rem">
    <div>
        <?php if (module_on('fees')): ?>
        <div class="card" style="padding:0">
            <h2 style="padding:1rem 1rem 0">School fees</h2>
            <div class="table-wrap"><table>
                <tr><th>Bill</th><th style="text-align:right">Total</th><th style="text-align:right">Paid</th><th style="text-align:right">Balance</th><th></th></tr>
                <?php foreach ($invoices as $i): $b = Fees::balance($i); ?>
                <tr><td><?= e($i['title']) ?><br><small class="muted"><?= e($i['invoice_no']) ?> · due <?= e(fmt_date($i['due_date'])) ?></small></td>
                    <td style="text-align:right"><?= e(number_format((float) $i['total'], 2)) ?></td><td style="text-align:right"><?= e(number_format((float) $i['paid'], 2)) ?></td>
                    <td style="text-align:right"><strong<?= $b > 0 ? ' style="color:var(--bad)"' : '' ?>><?= e(number_format(max(0, $b), 2)) ?></strong></td>
                    <td><a class="btn btn-sm" href="<?= e(url('pay/' . $i['public_key'])) ?>"><?= $b > 0 && PaymentGateway::active() !== 'none' ? 'Pay' : 'View' ?></a></td></tr>
                <?php endforeach; ?>
                <?php if (!$invoices): ?><tr><td colspan="5" class="muted">No bills.</td></tr><?php endif; ?>
            </table></div>
            <?php if ($payments): ?><div style="padding:0 1rem 1rem"><h3>Payments received</h3><?php foreach ($payments as $p): ?><p style="margin:0 0 .3rem;font-size:.9rem"><?= e(fmt_date($p['paid_on'])) ?> · <?= e(Fees::money($p['amount'])) ?> · <?= e(Fees::PAID_BY[$p['method']] ?? $p['method']) ?> · receipt <?= e($p['receipt_no']) ?></p><?php endforeach; ?></div><?php endif; ?>
            <?php if ($family && ($family['credit'] > 0.004 || $family['notes'] || $family['refunds'])): ?><div style="padding:0 1rem 1rem">
                <?php if ($family['credit'] > 0.004): ?><p class="alert alert-success" style="margin:0 0 .6rem">Family credit: <strong><?= e(Fees::money($family['credit'])) ?></strong> — money paid ahead. It is used on your next bill automatically.</p><?php endif; ?>
                <?php if ($family['notes']): ?><h3>Bill reductions (credit notes)</h3><?php foreach ($family['notes'] as $n): ?><p style="margin:0 0 .3rem;font-size:.9rem"><?= e(fmt_date(substr((string) $n['decided_at'], 0, 10))) ?> · <?= e(Fees::money($n['amount'])) ?> off <?= e($n['invoice_no']) ?> · <?= e($n['cn_no']) ?><br><small class="muted"><?= e($n['reason']) ?></small></p><?php endforeach; ?><?php endif; ?>
                <?php if ($family['refunds']): ?><h3>Refunds</h3><?php foreach ($family['refunds'] as $r): ?><p style="margin:0 0 .3rem;font-size:.9rem"><?= e(Fees::money($r['amount'])) ?> · <?= e($r['rf_no']) ?> · <?= $r['status'] === 'paid' ? 'paid ' . e(fmt_date($r['paid_on'])) . ' by ' . e(Family::REFUND_METHODS[$r['method']] ?? $r['method']) : 'being processed' ?></p><?php endforeach; ?><?php endif; ?>
            </div><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($periods && $timetable && module_on('timetable')): ?>
        <div class="card" style="padding:0">
            <h2 style="padding:1rem 1rem 0">Class time table</h2>
            <div class="table-wrap"><table class="tt-grid">
                <tr><th></th><?php foreach (Timetable::DAYS as $d => $dn): ?><th><?= e(substr($dn, 0, 3)) ?></th><?php endforeach; ?></tr>
                <?php foreach ($periods as $per): ?>
                <tr><td><small><?= e(substr($per['start_time'], 0, 5)) ?></small></td>
                    <?php foreach (Timetable::DAYS as $d => $dn): $s = $timetable[$d][$per['id']] ?? null; ?>
                    <td><small><?= $per['kind'] !== 'lesson' ? e($per['label']) : e($s['subject_name'] ?? '') ?></small></td>
                    <?php endforeach; ?></tr>
                <?php endforeach; ?>
            </table></div>
        </div>
        <?php endif; ?>
        <div class="card">
            <h2>Teachers and subjects</h2>
            <?php foreach ($teachers as $teacher): ?><p style="margin:0 0 .55rem"><a href="<?= e(url('teacher-profile/' . $teacher['id'])) ?>"><strong><?= e($teacher['name']) ?></strong></a><br><small class="muted"><?= !empty($teacher['is_class_teacher']) ? 'Class teacher' . ($teacher['subjects'] ? ' · ' . e($teacher['subjects']) : '') : e($teacher['subjects'] ?? '') ?></small></p><?php endforeach; ?>
            <?php if (!$teachers): ?><p class="muted">No teachers are linked to this class.</p><?php endif; ?>
        </div>
        <?php require BASE_PATH . '/app/views/pupils/_progress.php'; ?>
    </div>
    <div>
        <?php if (module_on('transport')): ?>
        <div class="card">
            <h2>School transport</h2>
            <?php if ($transport): ?>
                <p><strong><?= e($transport['route_name']) ?></strong><?= $transport['stop'] ? ' · ' . e($transport['stop']) : '' ?></p>
                <?php if ($transport['trips']): ?><p>Trips: <?= e(ucfirst($transport['trips'])) ?></p><?php endif; ?>
                <?php if ($transport['stops']): ?><p>Route stops: <?= e($transport['stops']) ?></p><?php endif; ?>
                <?php if ($transport['vehicle']): ?><p>Vehicle: <?= e($transport['vehicle']) ?></p><?php endif; ?>
                <?php if ($transport['driver_name']): ?><p>Driver: <?= e($transport['driver_name']) ?><?= $transport['driver_phone'] ? ' · ' . e($transport['driver_phone']) : '' ?></p><?php endif; ?>
            <?php else: ?><p class="muted">No active transport assignment is recorded.</p><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if (module_on('exams')): ?>
        <div class="card">
            <h2>Report cards</h2>
            <?php foreach ($reports as $r): ?><p style="margin:0 0 .4rem"><a href="<?= e(url('r/' . $r['token'])) ?>" target="_blank" rel="noopener"><?= e($r['name'] . ' · ' . $r['year_name']) ?></a></p><?php endforeach; ?>
            <?php if (!$reports): ?><p class="muted">No report card released yet.</p><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if (module_on('attendance')): ?>
        <div class="card">
            <h2>Absences &amp; lateness this term</h2>
            <?php foreach ($absences as $a): ?><p style="margin:0 0 .3rem;font-size:.9rem"><?= e(date('D j M', strtotime($a['date']))) ?> — <?= e(Attendance::STATUSES[$a['status']][0] ?? $a['status']) ?><?= $a['note'] ? ': ' . e($a['note']) : '' ?></p><?php endforeach; ?>
            <?php if (!$absences): ?><p class="muted">None recorded.</p><?php endif; ?>
            <?php if (empty($preview) && Auth::role() === 'parent' && module_on('leave')): ?><p style="margin:.6rem 0 0"><a class="btn btn-sm" href="<?= e(url('leave/create', ['pupil' => $child['id']])) ?>">Ask for leave</a> <a href="<?= e(url('leave')) ?>" style="font-size:.9rem">Leave requests</a></p><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($canteen) && module_on('canteen') && ($canteen['balance'] !== null || $canteen['plan'] || $canteen['menu'])): ?>
        <div class="card">
            <h2>Canteen</h2>
            <?php if ($canteen['balance'] !== null): ?><p style="margin:0 0 .4rem">Wallet balance: <strong><?= e(Fees::money($canteen['balance'])) ?></strong> <small class="muted">— top up at the accounts office (cash or MoMo)</small></p><?php endif; ?>
            <?php if ($canteen['plan']): ?><p style="margin:0 0 .4rem">Meal plan: <strong><?= e($canteen['plan']['name']) ?></strong></p><?php endif; ?>
            <?php foreach (Canteen::MEALS as $mk => $ml): if (empty($canteen['menu'][$mk])) continue; ?><p style="margin:0 0 .3rem;font-size:.9rem">Today's <?= e(strtolower($ml)) ?>: <?= e(implode(', ', array_column($canteen['menu'][$mk], 'name'))) ?></p><?php endforeach; ?>
            <?php foreach ($canteen['recent'] as $s): ?><p style="margin:0 0 .25rem;font-size:.85rem" class="muted"><?= e(date('D j M H:i', strtotime($s['created_at']))) ?> · <?= e($s['items']) ?> · <?= e(Fees::money($s['total'])) ?></p><?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (module_on('assignments')): ?>
        <div class="card">
            <h2>Homework</h2>
            <?php foreach ($homework as $h): [$hl, $ht] = Assignments::pupilState($h); ?>
                <p style="margin:0 0 .45rem;font-size:.9rem"><strong><?= e($h['title']) ?></strong> <small class="muted"><?= e($h['subject_name'] ?? '') ?> · due <?= e(date('D j M', strtotime($h['due_on']))) ?></small><br><?= badge($hl, $ht) ?><?= $h['sub_status'] === 'returned' && $h['score'] !== null ? ' <strong>' . e(Results::fmt((float) $h['score'])) . ($h['max_score'] !== null ? '/' . e(Results::fmt((float) $h['max_score'])) : '') . '</strong>' : '' ?><?= $h['sub_status'] === 'returned' && $h['feedback'] ? ' <small>“' . e($h['feedback']) . '”</small>' : '' ?><?= $h['attachment'] ? ' <a href="' . e(url('assignments/' . $h['id'] . '/file')) . '">📎</a>' : '' ?></p>
            <?php endforeach; ?>
            <?php if (!$homework): ?><p class="muted">No homework set.</p><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($quizzes && module_on('quizzes')): ?>
        <div class="card">
            <h2>Recent quizzes</h2>
            <?php foreach ($quizzes as $q): ?><p style="margin:0 0 .3rem;font-size:.9rem"><?= e($q['subject']) ?>: <?= e($q['title']) ?> — <strong><?= e(rtrim(rtrim((string) $q['score'], '0'), '.') ?: '0') ?>/<?= e(rtrim(rtrim((string) $q['max_score'], '0'), '.')) ?></strong></p><?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
