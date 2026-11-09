<div class="page-head">
    <div><h1>Feature guide</h1></div>
    <div class="actions"><a class="btn btn-ghost" href="<?= e(url('modules')) ?>">← NISDAS APPS</a></div>
</div>
<?php foreach (Modules::GROUPS as $g => [$gTitle, $gDesc]): if (empty($groups[$g])) continue; ?>
<section class="mod-group">
    <div class="mod-group-head">
        <div><h2><?= e($gTitle) ?></h2><p><?= e($gDesc) ?></p></div>
        <span class="mod-count"><?= count($groups[$g]) ?> feature<?= count($groups[$g]) === 1 ? '' : 's' ?></span>
    </div>
    <div class="mod-grid">
        <?php foreach ($groups[$g] as $k => $m): require __DIR__ . '/_card.php'; endforeach; ?>
    </div>
</section>
<?php endforeach; ?>
