<?php $ct = 'marketing'; ?>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="xgrid-box" data-xgrid="campaigns" data-xgrid-file="campaigns">
<div class="page-head">
    <div><h1>Campaigns</h1></div>
    <div class="actions"><?= export_grid_menu() ?><a class="btn btn-primary" href="<?= e(url('marketing/create')) ?>">+ New campaign</a></div>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['name' => ['Campaign'], 'to' => ['Sent to'], 'status' => ['Status'], 'sent' => ['Delivered', 'num', 'num'], 'read' => ['Read (WhatsApp)', 'num', 'num'], 'apps' => ['Applications after', 'num', 'num']]) ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $c): $r = $c['res']; ?><tr>
        <td data-c="name"><a href="<?= e(url('marketing/' . $c['id'])) ?>"><strong><?= e($c['name']) ?></strong></a></td>
        <td data-c="to"><?= e(Crm::AUDIENCES[$c['audience']] ?? $c['audience']) ?></td>
        <td data-c="status"><?= $c['status'] === 'sent' ? badge('Sent ' . fmt_date($c['sent_at'], 'j M'), 'good') : badge('Draft', 'neutral') ?></td>
        <td data-c="sent" class="num"><?= $r ? $r['sent'] . ' / ' . (int) $c['recipients'] : '' ?></td><td data-c="read" class="num"><?= $r ? $r['seen'] : '' ?></td><td data-c="apps" class="num"><?= $r ? $r['applications'] : '' ?></td>
    </tr><?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No campaigns yet.</p>
</div>
</div>
<div class="card" style="max-width:32rem;margin:1rem 0 0"><h2>Where enquiries come from (this year)</h2>
    <?php foreach ($bySource ?? [] as $s): ?><p style="margin:0 0 .35rem;display:flex;justify-content:space-between;gap:1rem"><span><?= e(Crm::SOURCES[$s['source']] ?? $s['source']) ?></span><span><strong><?= (int) $s['n'] ?></strong> <small class="muted">(<?= (int) $s['won'] ?> applied)</small></span></p><?php endforeach; ?>
    <?php if (empty($bySource)): ?><p class="muted">No enquiries yet this year.</p><?php endif; ?>
</div>
