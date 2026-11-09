<?php [$g, $mTitle, $mDesc, $ico, $tone, , $does, $who] = $m; ?>
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('modules')) ?>">NISDAS APPS</a> / <a href="<?= e(url('modules/guide')) ?>">Feature guide</a> / <?= e(Modules::GROUPS[$g][0]) ?></div></div>
</div>
<div class="card mod-detail" style="max-width:860px">
    <div style="display:flex;gap:1rem;align-items:center;margin-bottom:.8rem"><?= Modules::icon($ico, $tone, 64) ?><h1 style="margin:0"><?= e($mTitle) ?></h1></div>
    <p style="font-size:1.05rem"><?= e($mDesc) ?></p>
    <h2>What you can do</h2>
    <ul class="mod-list"><?php foreach ($does as $d): ?><li><?= e($d) ?></li><?php endforeach; ?></ul>
    <p class="muted"><strong>Who uses it:</strong> <?= e($who) ?></p>
    <div class="actions" style="justify-content:flex-start;margin-top:1rem">
        <a class="btn btn-primary" href="<?= e(url($route)) ?>">Open <?= e($mTitle) ?> →</a>
        <a class="btn btn-ghost" href="<?= e(url('help')) ?>">Help &amp; training guide</a>
    </div>
</div>
