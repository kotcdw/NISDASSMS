<?php
$tab = in_array($_GET['tab'] ?? '', ['categories', 'settings'], true) ? $_GET['tab'] : 'accounts';
$editId = (int) ($_GET['edit'] ?? 0);
$ea = $tab === 'accounts' && $editId ? current(array_filter($accounts, fn($a) => (int) $a['id'] === $editId)) : null;
$ec = $tab === 'categories' && $editId ? current(array_filter($categories, fn($c) => (int) $c['id'] === $editId)) : null;
?>
<div class="page-head"><div><h1>Accounts &amp; categories</h1></div>
    <div class="actions"><?php if ($tab === 'accounts'): ?><button type="button" class="btn btn-primary" data-help="acct-new">+ Add account</button><?php elseif ($tab === 'categories'): ?><button type="button" class="btn btn-primary" data-help="cat-new">+ Add category</button><?php endif; ?></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<nav class="seg"><?php foreach (['accounts' => 'Money accounts', 'categories' => 'Categories', 'settings' => 'Fee routing & requisition rules'] as $k => $l): ?><a href="<?= e(url('finance/setup', ['tab' => $k])) ?>"<?= $tab === $k ? ' class="active"' : '' ?>><?= e($l) ?></a><?php endforeach; ?></nav>

<?php if ($tab === 'accounts'): ?>
<div id="acct-new" class="help-box form-center" hidden>
    <form method="post" action="<?= e(url('finance/accounts')) ?>" class="card form"><?= csrf_field() ?>
        <h2>Add a money account</h2>
        <div class="grid-2">
            <label class="required">Name<input name="name" required maxlength="80" placeholder="e.g. GCB Current Account"></label>
            <label>Type<select name="kind"><?php foreach (Finance::ACCOUNT_KINDS as $k => $v): if ($k === 'petty') continue; ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select></label>
            <label>Account / wallet no.<input name="account_no" maxlength="60"></label>
            <label>Opening balance (GH₵)<input name="opening_balance" inputmode="decimal" value="0"></label>
            <label>As at<input type="date" name="opening_date" value="<?= e(date('Y-m-d')) ?>"></label>
        </div>
        
        <div class="vf-foot"><span></span><span style="display:flex;gap:.5rem"><button type="button" class="btn btn-ghost" data-help="acct-new">Cancel</button><button class="btn btn-primary" type="submit">Add account</button></span></div>
    </form>
</div>
<?php if ($ea): ?>
<div class="form-center">
    <form method="post" action="<?= e(url('finance/accounts/' . $ea['id'])) ?>" class="card form"><?= csrf_field() ?>
        <h2>Edit <?= e($ea['name']) ?></h2>
        <div class="grid-2">
            <label class="required">Name<input name="name" value="<?= e($ea['name']) ?>" required maxlength="80"></label>
            <label>Type<select name="kind"><?php foreach (Finance::ACCOUNT_KINDS as $k => $v): if ($k === 'petty') continue; ?><option value="<?= e($k) ?>"<?= selected($ea['kind'], $k) ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
            <label>Account / wallet no.<input name="account_no" value="<?= e($ea['account_no'] ?? '') ?>" maxlength="60"></label>
            <label>Opening balance (GH₵)<input name="opening_balance" value="<?= e(number_format((float) $ea['opening_balance'], 2, '.', '')) ?>" inputmode="decimal"></label>
            <label>As at<input type="date" name="opening_date" value="<?= e($ea['opening_date'] ?? '') ?>"></label>
            <label>Order in lists<input type="number" name="sort_order" value="<?= (int) $ea['sort_order'] ?>"></label>
        </div>
        
        <div class="vf-foot"><label style="font-weight:400;margin:0"><input type="checkbox" name="is_active" value="1"<?= checked((bool) $ea['is_active']) ?>> Active</label><span style="display:flex;gap:.5rem"><a class="btn btn-ghost" href="<?= e(url('finance/setup')) ?>">Cancel</a><button class="btn btn-primary" type="submit">Save</button></span></div>
    </form>
</div>
<?php endif; ?>
<div class="card" style="padding:0"><div class="table-wrap"><table>
    <thead><tr><th>Account</th><th>Type</th><th>No.</th><th class="num">Opening balance</th><th class="num">Balance now</th><th class="num">Entries</th><th></th></tr></thead>
    <tbody><?php foreach ($accounts as $a): ?><tr<?= $a['is_active'] ? '' : ' style="opacity:.55"' ?>>
        <td><strong><?= e($a['name']) ?></strong><?= $a['is_active'] ? '' : ' ' . badge('Inactive', 'neutral') ?></td>
        <td><?= e(Finance::ACCOUNT_KINDS[$a['kind']] ?? $a['kind']) ?></td><td><?= e($a['account_no'] ?? '') ?></td>
        <td class="num"><?= e(number_format((float) $a['opening_balance'], 2)) ?><?= $a['opening_date'] ? '<br><small class="muted">' . e(fmt_date($a['opening_date'], 'j M Y')) . '</small>' : '' ?></td>
        <td class="num"><strong><?= e(number_format($a['balance'], 2)) ?></strong></td><td class="num"><?= (int) $a['used'] ?></td>
        <td class="xg-act"><details class="rowmenu"><summary aria-label="Actions">⋯</summary><div>
            <?php if ($a['kind'] === 'petty'): ?><a href="<?= e(url('petty-cash/' . $a['id'])) ?>">Open petty cash box</a><?php else: ?><a href="<?= e(url('finance/setup', ['edit' => $a['id']])) ?>">Edit</a><?php endif; ?>
            <a href="<?= e(url('finance/cashbook', ['account' => $a['id']])) ?>">Cash book</a>
        </div></details></td>
    </tr><?php endforeach; ?></tbody>
</table></div></div>

<?php elseif ($tab === 'categories'): ?>
<div id="cat-new" class="help-box form-center" hidden>
    <form method="post" action="<?= e(url('finance/categories')) ?>" class="card form"><?= csrf_field() ?>
        <h2>Add a category</h2>
        <div class="grid-2">
            <label>Type<select name="kind"><option value="expense">Expense (money out)</option><option value="income">Income (money in)</option></select></label>
            <label class="required">Short code<input name="code" maxlength="12" required placeholder="e.g. SEC"></label>
            <label class="required">Name<input name="name" maxlength="80" required placeholder="e.g. Security services"></label>
            <label>Order in lists<input type="number" name="sort_order" value="20"></label>
        </div>
        <div class="vf-foot"><span></span><span style="display:flex;gap:.5rem"><button type="button" class="btn btn-ghost" data-help="cat-new">Cancel</button><button class="btn btn-primary" type="submit">Add category</button></span></div>
    </form>
</div>
<?php if ($ec): ?>
<div class="form-center">
    <form method="post" action="<?= e(url('finance/categories/' . $ec['id'])) ?>" class="card form"><?= csrf_field() ?>
        <h2>Edit <?= e($ec['name']) ?></h2>
        <div class="grid-2">
            <label>Short code<input name="code" value="<?= e($ec['code']) ?>" maxlength="12"<?= $ec['is_system'] ? ' readonly' : '' ?>></label>
            <label class="required">Name<input name="name" value="<?= e($ec['name']) ?>" maxlength="80" required></label>
            <label>Order in lists<input type="number" name="sort_order" value="<?= (int) $ec['sort_order'] ?>"></label>
        </div>
        <div class="vf-foot"><?php if (!$ec['is_system']): ?><label style="font-weight:400;margin:0"><input type="checkbox" name="is_active" value="1"<?= checked((bool) $ec['is_active']) ?>> Active</label><?php else: ?><span class="hint" style="margin:0">Used by the system — always active.</span><?php endif; ?><span style="display:flex;gap:.5rem"><a class="btn btn-ghost" href="<?= e(url('finance/setup', ['tab' => 'categories'])) ?>">Cancel</a><button class="btn btn-primary" type="submit">Save</button></span></div>
    </form>
</div>
<?php endif; ?>
<div class="xgrid-box" data-xgrid="fin-cats" data-xgrid-file="finance-categories">
    <div class="xg-bar">
        <input type="search" class="xg-search" data-xg-search placeholder="Search…" aria-label="Search">
        <select class="xg-pick-one" data-xg-pick="type" aria-label="Type"><option value="">Income &amp; expense</option><option>Income</option><option>Expense</option></select>
        <span class="xg-count" data-xg-count data-xg-noun="categories"><?= count($categories) ?> categories</span>
    </div>
    <div class="xg-scroll"><table class="xgrid">
        <thead><tr><th data-c="type">Type</th><th data-c="code">Code</th><th data-c="name">Name</th><th data-c="order" class="num">Order</th><th data-c="status">Status</th><th class="xg-act"></th></tr></thead>
        <tbody><?php foreach ($categories as $c): ?><tr<?= $c['is_active'] ? '' : ' style="opacity:.55"' ?>>
            <td data-c="type"><?= badge($c['kind'] === 'income' ? 'Income' : 'Expense', $c['kind'] === 'income' ? 'good' : 'warn') ?></td>
            <td data-c="code"><code><?= e($c['code']) ?></code></td><td data-c="name"><?= e($c['name']) ?></td>
            <td data-c="order" class="num"><?= (int) $c['sort_order'] ?></td>
            <td data-c="status"><?= $c['is_system'] ? '<small class="muted">System</small>' : ($c['is_active'] ? 'Active' : 'Inactive') ?></td>
            <td class="xg-act"><a class="btn btn-sm" href="<?= e(url('finance/setup', ['tab' => 'categories', 'edit' => $c['id']])) ?>">Edit</a></td>
        </tr><?php endforeach; ?></tbody>
    </table></div>
</div>

<?php else: ?>
<form method="post" action="<?= e(url('finance/settings')) ?>" class="card form form-center"><?= csrf_field() ?>
    <h2>Where fee payments go</h2>
    <div class="grid-2">
        <?php foreach (['fin_acct_cash' => 'Cash payments', 'fin_acct_momo' => 'Direct MoMo payments', 'fin_acct_bank' => 'Bank deposits & cheques', 'fin_acct_online' => 'Online (Paystack / Hubtel)'] as $k => $label): ?>
        <label><?= e($label) ?><select name="<?= e($k) ?>"><?php foreach ($accounts as $a): if ($a['kind'] === 'petty' || !$a['is_active']) continue; ?><option value="<?= (int) $a['id'] ?>"<?= selected(setting($k), $a['id']) ?>><?= e($a['name']) ?></option><?php endforeach; ?></select></label>
        <?php endforeach; ?>
    </div>
    <h2>Requisition &amp; purchasing rules</h2>
    <div class="grid-2">
        <label>Second approval (School Manager) above GH₵<input name="req_second_threshold" inputmode="decimal" value="<?= e(setting('req_second_threshold', '5000')) ?>"<?= can('finance.budget') || can('app.configure') ? '' : ' disabled' ?>></label>
        <label>Quotation required above GH₵ (0 = never)<input name="req_require_quote_above" inputmode="decimal" value="<?= e(setting('req_require_quote_above', '1000')) ?>"<?= can('finance.budget') || can('app.configure') ? '' : ' disabled' ?>></label>
        <label>Three quotations required above GH₵ (0 = never)<input name="pur_quote3_above" inputmode="decimal" value="<?= e(setting('pur_quote3_above', '5000')) ?>"<?= can('finance.budget') || can('app.configure') ? '' : ' disabled' ?>></label>
        <label>Head approves supplier bills above GH₵ before payment (0 = never)<input name="pur_pay_approval_above" inputmode="decimal" value="<?= e(setting('pur_pay_approval_above', '5000')) ?>"<?= can('finance.budget') || can('app.configure') ? '' : ' disabled' ?>></label>
    </div>
    
    <div class="vf-foot"><span></span><button class="btn btn-primary" type="submit">Save settings</button></div>
</form>
<?php endif; ?>
