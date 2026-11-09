<div class="page-center">
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('seating')) ?>">Seating plans</a> /</div><h1><?= e($class['name']) ?> seating</h1></div>
</div>
<form method="post" action="<?= e(url('seating/class/' . $class['id'])) ?>" class="form card">
    <?= csrf_field() ?>
    <?php $exam = false; $method = 'alpha'; require __DIR__ . '/_grid_form.php'; ?>
    <button class="btn btn-primary" type="submit">Make the seating plan</button>
</form>
</div>
