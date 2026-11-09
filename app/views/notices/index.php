<?php
$cat = (string) ($_GET['category'] ?? '');
$showAll = $manage && !empty($_GET['all']);
$aq = $showAll ? ['all' => 1] : [];
$catTabs = ['' => ['All', url('notices', $aq)]];
foreach (Notices::CATEGORIES as $k => [$l]) { $catTabs[$k] = [$l, url('notices', $aq + ['category' => $k])]; }
$cq = isset(Notices::CATEGORIES[$cat]) ? ['category' => $cat] : [];
$cols = ['date' => ['Date', 'date'], 'title' => ['Notice'], 'summary' => ['Summary'], 'kind' => ['Kind'], 'ref' => ['Ref no.'], 'state' => ['Status']];
if ($manage) { $cols['who'] = ['For']; }
?>
<div class="xgrid-box" data-xgrid="notices" data-xgrid-file="notices">
<div class="page-head">
    <div><h1>Notice board</h1></div>
    <div class="actions">
        <?php if ($manage): ?><a class="btn btn-ghost" href="<?= e(url('notices', $cq + ($showAll ? [] : ['all' => 1]))) ?>"><?= $showAll ? 'Hide scheduled &amp; expired' : 'Show scheduled &amp; expired' ?></a><?php endif; ?>
        <?= export_grid_menu() ?>
        <?php if ($manage): ?><a class="btn btn-primary" href="<?= e(url('notices/create')) ?>">+ New notice</a><?php endif; ?>
    </div>
</div>
<?= card_tabs($catTabs, isset(Notices::CATEGORIES[$cat]) ? $cat : '', 5, 'Notices to show') ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th($cols) ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $n):
        [$cl, $ct] = Notices::CATEGORIES[$n['category']];
        $future = $n['publish_at'] > date('Y-m-d H:i:s');
        $expired = $n['expires_on'] && $n['expires_on'] < date('Y-m-d');
        $flags = [];
        if (!$n['read_at']) { $flags[] = badge('New', 'info'); }
        if ($n['is_pinned']) { $flags[] = badge('Pinned', 'info'); }
        if (($n['priority'] ?? 'normal') !== 'normal') { $flags[] = badge(ucfirst($n['priority']), $n['priority'] === 'urgent' ? 'bad' : 'warn'); }
        if ($n['requires_ack'] && !$n['acknowledged_at'] && !can('notices.manage')) { $flags[] = badge('Please confirm you have read it', 'warn'); }
        if ($future) { $flags[] = badge('Scheduled ' . date('d M H:i', strtotime($n['publish_at']))); }
        if ($expired) { $flags[] = badge('Expired'); }
    ?>
    <tr<?= $n['read_at'] ? '' : ' class="unread"' ?>>
        <td data-c="date" data-s="<?= e((string) $n['publish_at']) ?>" style="white-space:nowrap"><?= e(date('D j M Y', strtotime($n['publish_at']))) ?></td>
        <td data-c="title"><a href="<?= e(url('notices/' . $n['id'])) ?>"><strong><?= e($n['title']) ?></strong></a></td>
        <td data-c="summary" class="muted"><?= e(mb_strimwidth(preg_replace('/\s+/', ' ', (string) $n['body']), 0, 70, '…')) ?></td>
        <td data-c="kind"><?= badge($cl, $ct) ?></td>
        <td data-c="ref"><?= e((string) ($n['circular_no'] ?? '')) ?></td>
        <td data-c="state"><?= implode('<span hidden> · </span> ', $flags) ?></td>
        <?php if ($manage): ?><td data-c="who"><?= e(Notices::label($n['audience'])) ?></td><?php endif; ?>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No notices at the moment.</p>
</div>
</div>
<div class="list-pager"><?= pager_links($pager) ?></div>
