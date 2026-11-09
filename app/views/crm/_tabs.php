<?php $ct = $ct ?? ''; $tabs = ['crm' => ['Enquiries', 'crm.manage'], 'marketing' => ['Campaigns', 'marketing.send'], 'marketing/optouts' => ['Do not message', 'marketing.send']]; ?>
<nav class="seg" data-pagenav>
<?php foreach ($tabs as $r => [$l, $perm]): if (!can($perm)) continue; ?><a<?= $ct === $r ? ' class="active"' : '' ?> href="<?= e(url($r)) ?>"><?= e($l) ?></a><?php endforeach; ?>
</nav>
