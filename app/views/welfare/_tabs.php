<nav class="seg" data-pagenav>
<?php foreach (['welfare' => 'Overview', 'welfare/behaviour/new' => 'Record behaviour', 'welfare/behaviour' => 'Behaviour records', 'welfare/cases' => 'Welfare cases'] as $wtR => $wtL): ?><a<?= ($wt ?? '') === $wtR ? ' class="active"' : '' ?> href="<?= e(url($wtR)) ?>"><?= e($wtL) ?></a><?php endforeach; ?>
</nav>
