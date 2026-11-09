<?php $action = $app ? url('admission/' . $app['id']) : url('admission'); ?>
<div class="page-center wide">
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('admission')) ?>">Admission</a> /</div>
    <h1><?= $app ? 'Edit ' . e($app['ref_no']) : 'Student Registration' ?></h1></div>
</div>
<div class="card">
    <form method="post" action="<?= e($action) ?>" class="form">
        <?= csrf_field() ?>
        <?php require __DIR__ . '/_fields.php'; ?>
        <button class="btn btn-primary" type="submit">Save application</button>
        <a class="btn btn-ghost" href="<?= e($app ? url('admission/' . $app['id']) : url('admission')) ?>">Cancel</a>
    </form>
</div>
</div>
