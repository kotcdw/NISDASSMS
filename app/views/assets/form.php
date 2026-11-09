<?php $a = $prefill ?? null; ?>
<div class="page-center">
<div class="page-head"><div><p class="breadcrumb"><a href="<?= e(url('fixed-assets')) ?>">Fixed assets</a> › Add</p><h1>Add an asset</h1></div></div>
<form method="post" action="<?= e(url('fixed-assets')) ?>" class="card form"><?= csrf_field() ?>
    <?php if (!empty($prefill)): ?><p class="alert alert-info">From supplier bill <?= e($prefill['bill_no']) ?>: check the details, then save.</p><input type="hidden" name="bill_id" value="<?= (int) $prefill['bill_id'] ?>"><?php endif; ?>
    <?php require __DIR__ . '/_fields.php'; ?>
    <div class="vf-foot"><label style="margin:0;display:flex;align-items:center;gap:.4rem;font-weight:400">How many identical items<input name="qty" inputmode="numeric" value="<?= e(old('qty', '1')) ?>" style="width:4.5rem"></label>
        <span style="display:flex;gap:.5rem"><a class="btn btn-ghost" href="<?= e(url('fixed-assets')) ?>">Cancel</a><button class="btn btn-primary" type="submit">Save</button></span></div>
</form>
</div>
