<?php
/* Panel frame. Expects $key, $title, $href (module page), $body (HTML), optional $note, $widget (old printable widget slug). */
?>
<?php $isEmpty = !empty($d['empty']); ?>
<section class="an-panel<?= $isEmpty ? ' is-empty' : '' ?>" data-card="<?= e($key) ?>" aria-labelledby="h-<?= e($key) ?>">
    <header class="an-ph">
        <h2 id="h-<?= e($key) ?>"><?= e($title) ?></h2>
        <?php if (!empty($href)): ?><a class="an-open" href="<?= e(url($href)) ?>">Open<span class="sr-only"> <?= e($title) ?></span> →</a><?php endif; ?>
    </header>
    <div class="an-pb"><?= $body ?></div>
    <?php if (!$isEmpty && (!empty($note) || !empty($widget))): ?>
    <footer class="an-pf">
        <span><?= e($note ?? '') ?></span>
        <?php if (!empty($widget)): $tq = !empty($d['term']['id']) ? ['term' => $d['term']['id']] : []; ?>
        <span class="an-dl"><a href="<?= e(url('dashboard/widget/' . $widget, $tq)) ?>" target="_blank">Print</a> · <a href="<?= e(url('dashboard/widget/' . $widget, $tq + ['export' => 1])) ?>">Excel</a></span>
        <?php endif; ?>
    </footer>
    <?php endif; ?>
</section>
