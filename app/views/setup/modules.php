<?php
/* Modules on/off (Administrator and Super Admin only). Expects $groups (group => [key => module]), $off (switched-off keys). */
$offCount = count($off);
$warn = !in_array('fees', $off, true) ? [] : array_values(array_filter(['canteen', 'events', 'shop'], fn($k) => !in_array($k, $off, true)));
?>
<div class="page-head"><div><h1>Modules on/off</h1></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<form method="post" action="<?= e(url('setup/modules')) ?>" class="form" data-confirm="Save which modules are on? Switched-off modules disappear for everyone straight away.">
    <?= csrf_field() ?>
    <?php if ($warn): ?><p class="alert alert-warning">Fees is off, but <?= e(implode(', ', array_map([ModuleSwitch::class, 'label'], $warn))) ?> bill parents through Fees — parents cannot pay those bills until Fees is on again.</p><?php endif; ?>
    <?php $gi = 0; ?>
    <div class="card ptabs" data-ptabs style="padding:0;margin-top:1rem">
        <div class="ptab-bar" role="tablist" aria-label="Module groups">
        <?php foreach ($groups as $group => $mods): $gi++; $onN = count(array_filter(array_keys($mods), fn($k) => !in_array($k, $off, true))); ?>
            <button type="button" role="tab" id="pt-g<?= $gi ?>" aria-controls="pp-g<?= $gi ?>" aria-selected="<?= $gi === 1 ? 'true' : 'false' ?>"<?= $gi === 1 ? '' : ' tabindex="-1"' ?> data-ptab="g<?= $gi ?>"><?= e($group) ?> <span class="ptab-n"><?= $onN ?>/<?= count($mods) ?></span></button>
        <?php endforeach; ?>
        </div>
        <?php $gi = 0; foreach ($groups as $group => $mods): $gi++; ?>
        <div class="ptab-panel mod-switch" role="tabpanel" id="pp-g<?= $gi ?>" aria-labelledby="pt-g<?= $gi ?>" data-ppanel="g<?= $gi ?>" style="padding:.4rem 1.2rem .6rem"<?= $gi === 1 ? '' : ' hidden' ?>>
            <?php foreach ($mods as $k => [$label, , $what]): $isOn = !in_array($k, $off, true); ?>
            <label class="mod-row">
                <span class="mod-text"><strong><?= e($label) ?></strong><small class="muted"><?= e($what) ?></small></span>
                <span class="toggle"><input type="checkbox" name="on[]" value="<?= e($k) ?>"<?= $isOn ? ' checked' : '' ?> aria-label="<?= e($label) ?> on"><span class="toggle-ui" aria-hidden="true"></span></span>
                <span class="badge <?= $isOn ? 'badge-good' : 'badge-bad' ?>" data-state><?= $isOn ? 'On' : 'Off' ?></span>
            </label>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="actions" style="margin-top:1rem"><button class="btn btn-primary" type="submit">Save changes</button></div>
</form>
