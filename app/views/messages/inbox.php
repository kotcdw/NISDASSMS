<div class="xgrid-box" data-xgrid="notifications" data-xgrid-file="notifications">
<div class="page-head">
    <div><h1>Notifications</h1></div>
    <div class="actions">
        <form method="post" action="<?= e(url('notifications/read-all')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn-ghost" type="submit">Mark all as read</button></form>
        <?= export_grid_menu() ?>
    </div>
</div>
<div class="card" data-push-box style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;margin:0;border-radius:0;border-left:0;border-right:0;border-top:0;box-shadow:none">
    <div style="flex:1 1 260px"><strong>Phone notifications</strong><br><small class="muted" data-push-status>Checking…</small></div>
    <button class="btn btn-primary" type="button" data-push-on>Turn on phone notifications</button>
    <button class="btn btn-ghost" type="button" data-push-off hidden>Turn off on this device</button>
    <form method="post" action="<?= e(url('push/test')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" type="submit">Send a test</button></form>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['when' => ['When', 'date'], 'title' => ['Notification'], 'text' => ['Details'], 'status' => ['Status']]) ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $n): ?>
    <tr>
        <td data-c="when" data-s="<?= e((string) $n['created_at']) ?>" style="white-space:nowrap"><?= e(fmt_date($n['created_at'], 'd M H:i')) ?></td>
        <td data-c="title"><a href="<?= e(url('notifications/' . $n['id'])) ?>"><strong><?= e($n['title']) ?></strong></a></td>
        <td data-c="text"><?= e((string) $n['body']) ?></td>
        <td data-c="status"><?= $n['read_at'] ? '<span class="muted">Read</span>' : badge('New', 'info') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>You have no notifications.</p>
</div>
</div>
<div class="list-pager"><?= pager_links($pager) ?></div>
