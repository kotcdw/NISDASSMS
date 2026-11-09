<?php $r = current_route(); $k = $_GET['kind'] ?? 'register'; ?>
<nav class="seg" data-pagenav aria-label="Health">
    <a href="<?= e(url('health')) ?>"<?= $r === 'health' ? ' class="active"' : '' ?>>Sick bay</a>
    <?php if (can('pupils.sensitive')): ?><a href="<?= e(url('health/report')) ?>"<?= $r === 'health/report' && $k !== 'nhis' ? ' class="active"' : '' ?>>Class health register</a>
    <a href="<?= e(url('health/report', ['kind' => 'nhis'])) ?>"<?= $r === 'health/report' && $k === 'nhis' ? ' class="active"' : '' ?>>NHIS</a><?php endif; ?>
</nav>
