<nav class="seg" data-pagenav aria-label="Governance">
<?php foreach (['governance' => 'Overview', 'governance/meetings' => 'Board meetings', 'governance/policies' => 'Policy register', 'governance/risks' => 'Risk register', 'governance/members' => 'Board members'] as $gtR => $gtL): ?><a href="<?= e(url($gtR)) ?>"<?= ($gt ?? '') === $gtR ? ' class="active"' : '' ?>><?= e($gtL) ?></a><?php endforeach; ?>
</nav>
