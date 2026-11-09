<div class="card" style="max-width:480px;margin:0 auto">
    <div class="alert alert-warning">TEST CHECKOUT — this page imitates a Mobile Money prompt. No money moves.</div>
    <h1><?= e(Fees::money($op['amount'])) ?></h1>
    <p>To <?= e(school_name()) ?> for bill <?= e($inv['invoice_no']) ?><br>From <?= e($op['payer_phone']) ?></p>
    <form method="post" action="<?= e(url('pay/test')) ?>" style="display:flex;gap:.6rem">
        <?= csrf_field() ?><input type="hidden" name="ref" value="<?= e($op['reference']) ?>">
        <button class="btn btn-good" type="submit" name="decision" value="approve">Approve payment</button>
        <button class="btn btn-danger" type="submit" name="decision" value="decline">Decline</button>
    </form>
</div>
