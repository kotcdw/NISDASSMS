<?php $pct = $total ? (int) round($done * 100 / $total) : 0; $group = null; ?>
<div class="page-head">
    <div><h1>Configure the school</h1></div>
    <div class="actions"><a class="btn" href="<?= e(url('golive')) ?>">Go-live checklist</a></div>
</div>
<div class="card" style="display:flex;gap:1.25rem;align-items:center;flex-wrap:wrap">
    <div style="flex:1 1 260px"><strong><?= (int) $done ?> of <?= (int) $total ?> required steps done</strong>
        <div class="meter" style="margin-top:.4rem" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><span style="width:<?= $pct ?>%"></span></div></div>
    <?php if ($next): ?><div><small class="muted">Next step</small><br><a class="btn btn-primary" href="#step-<?= e($next['key']) ?>"><?= e($next['title']) ?> →</a></div>
    <?php else: ?><div><?= badge('All required steps done', 'good') ?> <a href="<?= e(url('golive')) ?>">Continue with the trial run →</a></div><?php endif; ?>
</div>
<?php $n = 0; foreach ($steps as $s): $n++; if ($s['group'] !== $group): if ($group !== null) echo '</div>'; $group = $s['group']; ?>
<h2 style="margin:1.25rem 0 .6rem"><?= e($group) ?></h2><div class="card" style="padding:0">
<?php endif; $tone = $s['done'] === true ? 'good' : ($s['done'] === false ? 'bad' : 'warn'); ?>
    <div class="wiz-step" id="step-<?= e($s['key']) ?>">
        <div class="wiz-num wiz-<?= $tone ?>" aria-hidden="true"><?= $s['done'] === true ? '✓' : $n ?></div>
        <div class="wiz-body">
            <strong><?= e($s['title']) ?></strong> <?= badge($s['done'] === true ? 'Done' : ($s['done'] === false ? 'To do' : 'Optional / check'), $tone) ?>
            <div class="muted" style="font-size:.85rem"><?= e($s['why']) ?></div>
            <div style="font-size:.9rem;margin-top:.15rem"><?= e($s['detail']) ?></div>
        </div>
        <div class="wiz-actions">
            <?php if ($s['action']): ?>
                <form method="post" action="<?= e(url('setup/wizard')) ?>" data-confirm="<?= e($s['action'][1]) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="<?= e($s['action'][0]) ?>"><button class="btn btn-sm btn-primary" type="submit"><?= e($s['action'][1]) ?></button></form>
            <?php endif; ?>
            <?php if ($s['link']): ?><a class="btn btn-sm" href="<?= e(url($s['link'])) ?>">Open</a><?php endif; ?>
        </div>
    </div>
<?php endforeach; if ($group !== null) echo '</div>'; ?>
