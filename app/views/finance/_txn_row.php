<?php /** One transaction row. Needs $t. */ $void = $t['status'] === 'void'; $in = in_array($t['kind'], ['income', 'refund'], true); ?>
<tr<?= $void ? ' style="opacity:.55;text-decoration:line-through"' : '' ?>>
    <td style="white-space:nowrap"><?= e(fmt_date($t['txn_date'])) ?></td>
    <td><a href="<?= e(url('finance/transactions/' . $t['id'])) ?>"><?= e($t['txn_no']) ?></a></td>
    <td><?= e($t['description']) ?><?= $t['party'] ? '<br><small class="muted">' . e($t['party']) . '</small>' : '' ?></td>
    <td><small><?= e($t['category_name'] ?? ($t['kind'] === 'transfer' ? 'Transfer' : '')) ?></small></td>
    <td><small><?= e($t['account_name']) ?><?= $t['kind'] === 'transfer' ? ' → ' . e($t['to_account_name'] ?? '') : '' ?></small></td>
    <td style="text-align:right;white-space:nowrap;color:<?= $t['kind'] === 'transfer' ? 'inherit' : ($in ? 'var(--good)' : 'var(--bad)') ?>"><?= $t['kind'] === 'transfer' ? '' : ($in ? '+' : '−') ?><?= e(number_format((float) $t['amount'], 2)) ?></td>
</tr>
