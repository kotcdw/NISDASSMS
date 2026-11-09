<?php $rq = $req; ?>
<div class="page-center">
<div class="page-head"><div><p class="breadcrumb"><a href="<?= e(url('suppliers')) ?>">Bills to pay</a> › New</p><h1>New supplier bill</h1></div></div>
<?php if ($rq): ?><div class="alert alert-info">From requisition <a href="<?= e(url('requisitions/' . $rq['id'])) ?>"><?= e($rq['req_no']) ?></a> — approved <?= e(Fees::money($rq['approved_amount'] ?? $rq['amount'])) ?>. When this bill is fully paid the requisition is closed automatically.</div><?php endif; ?>
<form method="post" action="<?= e(url('suppliers/bills')) ?>" enctype="multipart/form-data" class="card form"<?= old('supply_type') === '' ? ' data-fresh' : '' ?> data-wht-form data-rates="<?= e(json_encode(['goods' => Suppliers::rate('goods'), 'works' => Suppliers::rate('works'), 'services' => Suppliers::rate('services'), 'exempt' => 0, 'threshold' => Suppliers::threshold()])) ?>">
    <?= csrf_field() ?>
    <?php if ($rq): ?><input type="hidden" name="requisition_id" value="<?= (int) $rq['id'] ?>"><?php endif; ?>
    <div class="grid-2">
        <label class="required">Supplier<select name="supplier_id" required data-wht-supplier>
            <option value="">Choose…</option>
            <?php foreach ($suppliers as $s): ?><option value="<?= (int) $s['id'] ?>" data-type="<?= e($s['supply_type']) ?>" data-cat="<?= (int) $s['category_id'] ?>"<?= selected(old('supplier_id', $sel ?: ''), $s['id']) ?>><?= e($s['name']) ?></option><?php endforeach; ?>
        </select><small><a href="<?= e(url('suppliers/list', ['new' => 1])) ?>">+ Add a new supplier</a></small></label>
        <label>Supplier's invoice no.<input name="supplier_ref" maxlength="60" value="<?= e(old('supplier_ref')) ?>"></label>
        <label class="required">Bill date<input type="date" name="bill_date" required max="<?= e(date('Y-m-d')) ?>" value="<?= e(old('bill_date', date('Y-m-d'))) ?>"></label>
        <label>Pay by (due date)<input type="date" name="due_date" value="<?= e(old('due_date', date('Y-m-d', strtotime('+30 days')))) ?>"></label>
        <label class="required">Amount on the invoice (GH₵)<input name="amount" inputmode="decimal" required data-wht-amount value="<?= e(old('amount', $rq ? number_format((float) ($rq['approved_amount'] ?? $rq['amount']), 2, '.', '') : '')) ?>"></label>
        <label class="required">Category<select name="category_id" required data-wht-cat><option value="">Choose…</option><?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected(old('category_id', $rq['category_id'] ?? ''), $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
        <?php if (Finance::hasDepartments()): $bfDept = (string) old('department', $rq['department'] ?? ''); ?><label>Department (for its budget cap)<select name="department"><option value="">—</option><?php foreach (Finance::departments() as $dn): ?><option value="<?= e($dn) ?>"<?= selected($bfDept, $dn) ?>><?= e($dn) ?></option><?php endforeach; ?></select></label><?php endif; ?>
        <label>Type of supply<select name="supply_type" data-wht-type><?php foreach (Suppliers::TYPES as $k => $l): ?><option value="<?= $k ?>"<?= selected(old('supply_type'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
        <label>Invoice<input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png"></label>
    </div>
    <label class="required">What was supplied<input name="description" required maxlength="255" value="<?= e(old('description', $rq['title'] ?? '')) ?>" placeholder="e.g. 40 dual desks for JHS 1"></label>
    <label style="font-weight:400"><input type="checkbox" name="no_wht" value="1" data-wht-none<?= checked(old('no_wht')) ?>> WHT exempt</label>
    <p class="fp-sum" data-wht-out>Withholding tax: <strong>0.00</strong></p>
    <div class="vf-foot"><span></span><span style="display:flex;gap:.5rem"><a class="btn btn-ghost" href="<?= e(url('suppliers')) ?>">Cancel</a><button class="btn btn-primary" type="submit">Save bill</button></span></div>
</form>
</div>
