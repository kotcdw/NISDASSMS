<?php
$msgList = true;
require __DIR__ . '/_tabs.php';
$tone = ['queued' => 'warn', 'sent' => 'good', 'failed' => 'bad', 'cancelled' => 'neutral'];
$batch = (int) ($_GET['batch'] ?? 0);
$status = (string) ($_GET['status'] ?? '');
$bq = $batch ? ['batch' => $batch] : [];
$tabs = ['' => ['All', url('messages/outbox', $bq)]];
foreach (['queued' => 'Waiting', 'sent' => 'Sent', 'failed' => 'Failed', 'cancelled' => 'Cancelled'] as $k => $l) { $tabs[$k] = [$l, url('messages/outbox', $bq + ['status' => $k])]; }
?>
<div class="xgrid-box" data-xgrid="outbox" data-xgrid-file="outbox">
<div class="page-head">
    <div><h1>Outbox</h1></div>
    <div class="actions">
        <?php if ($batch): ?><a class="btn btn-ghost" href="<?= e(url('messages/outbox', $status !== '' ? ['status' => $status] : [])) ?>">All messages</a><?php endif; ?>
        <?= export_grid_menu() ?>
        <form method="post" action="<?= e(url('messages/process')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn-primary" type="submit">Send waiting messages now (<?= (int) $queued ?>)</button></form>
    </div>
</div>
<?= card_tabs($tabs, isset($tabs[$status]) ? $status : '', 5, 'Messages to show') ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['queued' => ['Queued', 'date'], 'for' => ['Send at', 'date'], 'name' => ['To'], 'phone' => ['Phone'], 'msg' => ['Message'], 'channel' => ['Channel'], 'kind' => ['Type'], 'status' => ['Status'], 'delivery' => ['Delivery'], 'error' => ['Problem']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $m):
        $later = $m['scheduled_at'] > $m['created_at'] && $m['status'] === 'queued';
        $del = [];
        if ($m['provider'] === 'log' && $m['status'] === 'sent') { $del[] = 'Test mode'; }
        if (!empty($m['read_at'])) { $del[] = badge('Read ' . fmt_date($m['read_at'], 'd M H:i'), 'good'); } elseif (!empty($m['delivery_status'])) { $del[] = e(ucfirst($m['delivery_status'])); }
    ?>
    <tr>
        <td data-c="queued" data-s="<?= e((string) $m['created_at']) ?>" style="white-space:nowrap"><?= e(fmt_date($m['created_at'], 'd M H:i')) ?></td>
        <td data-c="for" data-s="<?= $later ? e((string) $m['scheduled_at']) : '' ?>" style="white-space:nowrap"><?= $later ? e(fmt_date($m['scheduled_at'], 'd M H:i')) : '' ?></td>
        <td data-c="name"><?= e($m['recipient_name'] ?: '—') ?></td>
        <td data-c="phone" style="white-space:nowrap"><?= e($m['recipient']) ?></td>
        <td data-c="msg"><?= e(mb_strimwidth($m['event_key'] === 'parent_login' ? Notifier::redact($m['body']) : $m['body'], 0, 140, '…')) ?></td>
        <td data-c="channel"><?= ($m['channel'] ?? 'sms') === 'whatsapp' ? badge('WhatsApp', 'good') : 'SMS' ?></td>
        <td data-c="kind"><?= e(str_replace('_', ' ', (string) $m['event_key'])) ?></td>
        <td data-c="status"><?= badge(ucfirst($m['status']), $tone[$m['status']]) ?></td>
        <td data-c="delivery" style="white-space:nowrap"><?= implode('<span hidden> · </span> ', $del) ?></td>
        <td data-c="error"><?= $m['last_error'] ? '<span style="color:var(--bad)">' . e($m['last_error']) . '</span>' : '' ?></td>
        <td class="xg-act">
            <?php if (can('messages.manage') && $m['status'] === 'queued'): ?>
                <form method="post" action="<?= e(url('messages/' . $m['id'] . '/cancel')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" type="submit">Cancel</button></form>
            <?php elseif (can('messages.manage') && $m['status'] === 'failed'): ?>
                <form method="post" action="<?= e(url('messages/' . $m['id'] . '/retry')) ?>"><?= csrf_field() ?><button class="btn btn-sm" type="submit">Retry</button></form>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>Nothing here.</p>
</div>
</div>
<div class="list-pager"><?= pager_links($pager) ?></div>
