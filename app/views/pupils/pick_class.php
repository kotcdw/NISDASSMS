<?php /** Choose the class to print a class list / mark sheet / ID cards for ($kind = 'class-list' or 'id-cards'). */ ?>
<div class="page-head">
    <div><h1><?= e($title) ?></h1><p class="muted" style="margin:0">Choose a class. The <?= $kind === 'id-cards' ? 'cards open' : 'list opens' ?> in a new tab, ready to print or save as PDF.</p></div>
</div>
<div class="card pick-class">
    <?php foreach ($classes as $c): ?>
    <div class="pick-row">
        <div class="pick-name"><strong><?= e($c['name']) ?></strong>
            <span class="muted"><?= (int) $c['students'] ?> student<?= (int) $c['students'] === 1 ? '' : 's' ?><?= !empty($c['teacher']) ? ' · ' . e($c['teacher']) : '' ?></span></div>
        <div class="pick-actions">
            <?php if ((int) $c['students'] === 0): ?><span class="muted">No students yet</span>
            <?php elseif ($kind === 'id-cards'): ?>
                <a class="btn btn-sm btn-primary" href="<?= e(url('pupils/id-cards', ['class' => $c['id']])) ?>" target="_blank">Print ID cards</a>
            <?php else: ?>
                <a class="btn btn-sm btn-primary" href="<?= e(url('pupils/class-list', ['class' => $c['id']])) ?>" target="_blank">Class list</a>
                <a class="btn btn-sm" href="<?= e(url('pupils/class-list', ['class' => $c['id'], 'cols' => 10])) ?>" target="_blank">Blank mark sheet</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (!$classes): ?><p class="muted" style="margin:0">No classes yet. Add classes in Administration → School setup → Classes.</p><?php endif; ?>
</div>
