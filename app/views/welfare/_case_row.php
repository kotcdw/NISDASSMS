<?php /* expects $c */ [$kl, $kt] = Welfare::KINDS[$c['kind']]; [$sl, $st] = Welfare::STATUS[$c['status']]; [$rl, $rt] = Welfare::RISK[$c['risk']]; $late = $c['status'] !== 'closed' && $c['follow_up_on'] && $c['follow_up_on'] < date('Y-m-d'); ?>
<tr>
    <td data-c="ref"><a href="<?= e(url('welfare/cases/' . $c['id'])) ?>"><?= e($c['ref_no']) ?></a></td>
    <td data-c="student"><?= e($c['first_name'] . ' ' . $c['last_name']) ?></td>
    <td data-c="case"><?= badge($kl, $kt) ?><span hidden>: </span><br><small><?= e($c['kind'] === 'safeguarding' && !can('welfare.safeguarding') ? 'Concern raised' : $c['title']) ?></small></td>
    <td data-c="risk"><?= badge($rl, $rt) ?></td>
    <td data-c="by"><?= e($c['assigned_name'] ?? ($c['kind'] === 'safeguarding' ? 'Safeguarding lead' : '—')) ?></td>
    <td data-c="follow" data-s="<?= e((string) $c['follow_up_on']) ?>"><?= $c['follow_up_on'] ? e(fmt_date($c['follow_up_on'], 'D j M')) . ($late ? ' ' . badge('Overdue', 'bad') : '') : '—' ?></td>
    <td data-c="status"><?= badge($sl, $st) ?></td>
</tr>
