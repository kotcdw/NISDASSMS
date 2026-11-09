<div class="page-center">
<div class="page-head">
    <div><p class="breadcrumb"><a href="<?= e(url('finance')) ?>">Finance</a> › <?= e($title) ?></p><h1><?= e($title) ?></h1>
        </div>
</div>
<form method="post" action="<?= e(url('finance/transactions')) ?>" enctype="multipart/form-data" class="card form">
    <?= csrf_field() ?>
    <input type="hidden" name="kind" value="<?= e($kind) ?>">
    <div class="grid-2">
        <label>Date<input type="date" name="txn_date" required value="<?= e(old('txn_date', date('Y-m-d'))) ?>" max="<?= e(date('Y-m-d')) ?>"></label>
        <label>Amount (GH₵)<input name="amount" inputmode="decimal" required value="<?= e(old('amount')) ?>"></label>
    </div>
    <div class="grid-2">
        <label><?= $kind === 'income' ? 'Paid into' : ($kind === 'transfer' ? 'From account' : 'Paid from') ?>
            <select name="account_id" required<?= QuickAddController::attr('fin_account') ?>><?php foreach ($accounts as $a): ?><option value="<?= (int) $a['id'] ?>"<?= selected(old('account_id'), $a['id']) ?>><?= e($a['name']) ?> (<?= e(number_format(Finance::balance((int) $a['id']), 2)) ?>)</option><?php endforeach; ?></select></label>
        <?php if ($kind === 'transfer'): ?>
        <label>To account<select name="to_account_id" required<?= QuickAddController::attr('fin_account') ?>><?php foreach ($accounts as $i => $a): ?><option value="<?= (int) $a['id'] ?>"<?= selected(old('to_account_id', $accounts[1]['id'] ?? ''), $a['id']) ?>><?= e($a['name']) ?></option><?php endforeach; ?></select></label>
        <?php else: ?>
        <label>Category<select name="category_id" required<?= QuickAddController::attr('fin_category', ['category_kind' => $kind === 'income' ? 'income' : 'expense']) ?>><option value="">— choose —</option><?php foreach ($categories as $c): if ($c['code'] === 'FEES') continue; ?><option value="<?= (int) $c['id'] ?>"<?= selected(old('category_id'), $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
        <?php if ($kind !== 'income' && Finance::hasDepartments()): ?><label>Department (for its budget cap)<select name="department"><option value="">—</option><?php foreach (Finance::departments() as $dn): ?><option value="<?= e($dn) ?>"<?= selected(old('department'), $dn) ?>><?= e($dn) ?></option><?php endforeach; ?></select></label><?php endif; ?>
        <?php endif; ?>
    </div>
    <label>Description<input name="description" required maxlength="255" value="<?= e(old('description')) ?>" placeholder="<?= $kind === 'income' ? 'e.g. Donation from Alumni Association' : ($kind === 'transfer' ? 'e.g. Cash banked at GCB' : 'e.g. ECG prepaid units, September') ?>"></label>
    <div class="grid-2">
        <label><?= $kind === 'income' ? 'Received from' : ($kind === 'transfer' ? 'Done by' : 'Paid to') ?><input name="party" maxlength="150" value="<?= e(old('party')) ?>"></label>
        <label>Reference<input name="reference" maxlength="100" value="<?= e(old('reference')) ?>"></label>
    </div>
    <label>Receipt<input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png"></label>
    <?php if ($kind !== 'income'): ?><label style="font-weight:400"><input type="checkbox" name="allow_overdraw" value="1"> Allow overdraft</label><?php endif; ?>
    <div style="display:flex;gap:.6rem;flex-wrap:wrap">
        <button class="btn btn-primary" type="submit">Save</button>
        <button class="btn" type="submit" name="another" value="1">Save and add another</button>
        <a class="btn btn-ghost" href="<?= e(url('finance/transactions')) ?>">Cancel</a>
    </div>
</form>
</div>
