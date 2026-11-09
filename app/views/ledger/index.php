<?php $groups = []; foreach ($chart as $a) { $groups[$a['type']][] = $a; } ?>
<div class="xgrid-box" data-xgrid="accounts" data-xgrid-file="chart-of-accounts">
<div class="page-head">
    <div><h1>Accounts</h1></div>
    <div class="actions"><?= export_grid_menu() ?><?php if (can('finance.manage')): ?><button type="button" class="btn btn-primary" data-help="new-account" aria-expanded="false">+ Add account</button><?php endif; ?></div>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="alert <?= $balanced ? 'alert-success' : 'alert-error' ?>" role="status"><?= $balanced ? '✓ The books balance — total debits equal total credits.' : 'The books do not balance. Tell the system administrator.' ?><?php if ($pending): ?> · <a href="<?= e(url('ledger/journals')) ?>"><?= $pending ?> manual journal<?= $pending > 1 ? 's' : '' ?> waiting for approval</a><?php endif; ?></div>
<?php if (can('finance.manage')): ?>
<div id="new-account" class="card" hidden>
    <h2>Add an account</h2>
    <form method="post" action="<?= e(url('ledger/accounts')) ?>" class="form"><?= csrf_field() ?>
        <div class="grid-2">
            <label class="required">Kind<select name="type" required><option value="">Choose…</option><?php foreach (Ledger::TYPES as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select></label>
            <label class="required">Code<input name="code" inputmode="numeric" maxlength="4" required placeholder="e.g. 2400"></label>
        </div>
        <label class="required">Name<input name="name" maxlength="120" required placeholder="e.g. Loan from the Conference"></label>
        <div class="gate-actions"><button class="btn btn-primary" type="submit">Add account</button><button type="button" class="btn btn-ghost" data-help="new-account">Cancel</button></div>
    </form>
</div>
<?php endif; ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['code' => ['Code'], 'name' => ['Account'], 'kind' => ['Kind'], 'bal' => ['Balance (GH₵)', 'num', 'num'], 'state' => ['Status']]) ?></tr></thead>
    <tbody>
    <?php foreach (Ledger::TYPES as $type => $label): if (empty($groups[$type])) continue; ?>
        <?php foreach ($groups[$type] as $a): $on = (int) $a['is_active']; $has = abs($a['balance']) >= 0.005; ?>
        <tr<?= $on ? '' : ' class="muted"' ?>>
            <td data-c="code"><code><?= e($a['code']) ?></code></td>
            <td data-c="name"><a href="<?= e(url('ledger/accounts/' . $a['id'])) ?>"><?= e($a['name']) ?></a></td>
            <td data-c="kind"><?= e($label) ?></td>
            <td data-c="bal" class="num" data-s="<?= $has ? round((float) $a['balance'], 2) : 0 ?>"><?= $has ? e(number_format($a['balance'], 2)) : '' ?></td>
            <td data-c="state"><?= $on ? 'In use' : 'Not in use' ?></td>
        </tr>
        <?php endforeach; ?>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $chart ? ' hidden' : '' ?> data-xg-empty>No accounts yet.</p>
</div>
</div>
