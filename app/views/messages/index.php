<?php $msgList = true; require __DIR__ . '/_tabs.php'; ?>
<div class="xgrid-box" data-xgrid="message-history" data-xgrid-file="messages-sent">
<div class="page-head">
    <div><h1>Notifications &amp; messages</h1></div>
    <div class="actions">
        <a class="btn btn-ghost" href="<?= e(url('messages/outbox')) ?>">Outbox</a>
        <?= export_grid_menu() ?>
        <a class="btn btn-primary" href="<?= e(url('messages/compose')) ?>">+ Send message</a>
    </div>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['sent' => ['Sent', 'date'], 'title' => ['Message'], 'text' => ['Text'], 'to' => ['To'], 'n' => ['Recipients', 'num', 'num'], 'status' => ['Status'], 'by' => ['By']]) ?></tr></thead>
    <tbody>
    <?php foreach ($batches as $b): ?>
    <tr>
        <td data-c="sent" data-s="<?= e((string) $b['created_at']) ?>" style="white-space:nowrap"><?= e(fmt_date($b['created_at'], 'd M Y H:i')) ?></td>
        <td data-c="title"><a href="<?= e(url('messages/outbox', ['batch' => $b['id']])) ?>"><strong><?= e($b['title']) ?></strong></a></td>
        <td data-c="text"><?= e(mb_strimwidth((string) $b['body'], 0, 110, '…')) ?></td>
        <td data-c="to"><?= e($b['audience_label']) ?></td>
        <td data-c="n" class="num"><?= (int) $b['recipients'] ?></td>
        <td data-c="status" style="white-space:nowrap"><?= badge((int) $b['sent'] . ' sent', 'good') ?><?= $b['queued'] ? '<span hidden> · </span> ' . badge((int) $b['queued'] . ' waiting', 'warn') : '' ?><?= $b['failed'] ? '<span hidden> · </span> ' . badge((int) $b['failed'] . ' failed', 'bad') : '' ?></td>
        <td data-c="by"><?= e($b['author'] ?? 'System') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $batches ? ' hidden' : '' ?> data-xg-empty>No messages sent yet. <a href="<?= e(url('messages/compose')) ?>">Send the first one</a>.</p>
</div>
</div>
<div class="list-pager"><?= pager_links($pager) ?></div>
