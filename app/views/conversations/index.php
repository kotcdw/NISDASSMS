<?php $convTabs = ['mine' => ['My conversations', url('conversations')], 'all' => ['All conversations (oversight)', url('conversations', ['all' => 1])]]; ?>
<div class="xgrid-box" data-xgrid="conversations" data-xgrid-file="conversations">
<div class="page-head">
    <div><h1><?= e($title) ?></h1></div>
    <div class="actions"><?= export_grid_menu() ?><a class="btn btn-primary" href="<?= e(url('conversations/create')) ?>">+ New message</a></div>
</div>
<?php if (can('chat.oversee')): ?><?= card_tabs($convTabs, $all ? 'all' : 'mine', 2, 'Conversations to show') ?><?php endif; ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['subject' => ['Subject'], 'last' => ['Last message'], 'with' => ['With'], 'student' => ['Student'], 'when' => ['When', 'date']]) ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $unread = $r['last_read_at'] === null ? !$all : $r['last_message_at'] > $r['last_read_at']; ?>
    <tr class="conv-row<?= $unread ? ' unread' : '' ?>">
        <td data-c="subject"><a href="<?= e(url('conversations/' . $r['id'])) ?>"><?= $unread ? '<strong>' . e($r['subject']) . '</strong>' : e($r['subject']) ?></a><?= $unread ? '<span hidden> · </span> ' . badge('New', 'info') : '' ?><?= (int) $r['is_closed'] ? '<span hidden> · </span> ' . badge('Closed') : '' ?></td>
        <td data-c="last" class="muted"><?= e(mb_strimwidth((string) $r['last_body'], 0, 90, '…')) ?></td>
        <td data-c="with"><?= e($r['others'] ?? '') ?></td>
        <td data-c="student"><?= e(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))) ?></td>
        <td data-c="when" data-s="<?= e((string) $r['last_message_at']) ?>" style="white-space:nowrap"><?= e(date('d M H:i', strtotime($r['last_message_at']))) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No messages yet.</p>
</div>
</div>
<div class="list-pager"><?= pager_links($pager) ?></div>
