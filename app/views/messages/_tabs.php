<?php
$r = current_route();
$tabs = ['messages' => 'History', 'messages/compose' => 'Send message', 'messages/outbox' => 'Outbox'];
?>
<?php if (empty($msgList)): /* list pages (History, Outbox) put their own title inside the list box */ ?>
<div class="page-head">
    <div><h1><?= e($tabs[$r] ?? ($title ?? 'Messages')) ?></h1></div>
</div>
<?php endif; ?>
<nav class="seg" data-pagenav>
    <?php foreach ($tabs as $route => $label): ?>
        <a href="<?= e(url($route)) ?>"<?= $r === $route ? ' class="active"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>
