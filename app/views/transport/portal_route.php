<div class="page-head">
    <div><p class="breadcrumb"><a href="<?= e(url('transport')) ?>">My transport</a></p><h1><?= e($route['name']) ?></h1></div>
</div>
<div class="cols">
    <div class="card">
        <h2>Route details</h2>
        <?php foreach (['stops' => 'Stops', 'vehicle' => 'Vehicle', 'driver_name' => 'Driver', 'driver_phone' => 'Driver phone', 'attendant_name' => 'Attendant', 'attendant_phone' => 'Attendant phone'] as $key => $label): ?>
            <?php if ($route[$key]): ?><p><strong><?= e($label) ?>:</strong> <?= e($route[$key]) ?></p><?php endif; ?>
        <?php endforeach; ?>
    </div>
    <div class="card">
        <h2>Your assignment<?= count($assigned) > 1 ? 's' : '' ?></h2>
        <?php foreach ($assigned as $student): ?>
            <p><strong><?= e(trim($student['first_name'] . ' ' . $student['last_name'])) ?></strong><?= $student['class_name'] ? ' · ' . e($student['class_name']) : '' ?><br>
                <small class="muted"><?= e($student['stop'] ?: 'Stop not recorded') ?> · <?= e(TransportController::TRIPS[$student['trips']] ?? $student['trips']) ?></small></p>
        <?php endforeach; ?>
    </div>
</div>
