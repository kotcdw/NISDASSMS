<div class="page-center">
<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('crm')) ?>">CRM</a> /</div><h1>New enquiry</h1></div></div>
<form method="post" action="<?= e(url('crm')) ?>" class="form card" style="max-width:780px"><?= csrf_field() ?>
    <?php if (old('allow_duplicate')): ?><input type="hidden" name="allow_duplicate" value="1"><?php endif; ?>
    <?php require __DIR__ . '/_fields.php'; ?>
    <button class="btn btn-primary" type="submit">Save enquiry</button>
</form>
</div>
