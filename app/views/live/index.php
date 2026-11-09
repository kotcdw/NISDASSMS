<?php
$pq = $prov ? ['provider' => $prov] : [];
$whenTabs = ['coming' => ['Today & coming', url('live', $pq)], 'past' => ['Past', url('live', ['when' => 'past'] + $pq)]];
?>
<div class="xgrid-box" data-xgrid="live" data-xgrid-file="live-classes">
<div class="page-head">
    <div><h1>Live classes</h1></div>
    <div class="actions">
        <form method="get" action="<?= e(url('live')) ?>" class="list-pick" data-autosubmit>
            <?php if ($when === 'past'): ?><input type="hidden" name="when" value="past"><?php endif; ?>
            <select name="provider" aria-label="Platform"><option value="">All platforms</option><?php foreach (['zoom', 'meet', 'teams'] as $k): ?><option value="<?= $k ?>"<?= selected($k, $prov) ?>><?= e(Learning::PROVIDERS[$k]) ?></option><?php endforeach; ?></select>
        </form>
        <?= export_grid_menu() ?>
        <a class="btn btn-primary" href="<?= e(url('live/create', $pq)) ?>">+ Schedule a live class</a>
    </div>
</div>
<?= card_tabs($whenTabs, $when === 'past' ? 'past' : 'coming', 2, 'Live classes to show') ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['when' => ['When', 'date'], 'class' => ['Class'], 'subject' => ['Subject'], 'title' => ['Title'], 'on' => ['On'], 'min' => ['Minutes', 'num', 'num'], 'joined' => ['Joined', 'num', 'num']]) ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $s): ?><tr<?= $s['status'] === 'cancelled' ? ' style="opacity:.55"' : '' ?>>
        <td data-c="when" data-s="<?= e($s['starts_at']) ?>" style="white-space:nowrap"><?= e(fmt_date($s['starts_at'], 'D j M')) ?> <strong><?= e(date('H:i', strtotime($s['starts_at']))) ?></strong></td>
        <td data-c="class"><?= e($s['class_name']) ?></td>
        <td data-c="subject"><?= e($s['subject_name'] ?? '') ?></td>
        <td data-c="title"><a href="<?= e(url('live/' . $s['id'])) ?>"><strong><?= e($s['title']) ?></strong></a><?= $s['status'] === 'cancelled' ? '<span hidden> · </span> ' . badge('Cancelled', 'neutral') : '' ?></td>
        <td data-c="on" style="white-space:nowrap"><?= Engine::icon($s['provider'] === 'other' ? 'live' : $s['provider'], 20) ?> <?= e(Learning::PROVIDERS[$s['provider']]) ?></td>
        <td data-c="min" class="num"><?= (int) $s['duration_min'] ?></td>
        <td data-c="joined" class="num"><?= (int) $s['joined'] ?></td>
    </tr><?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No live classes <?= $when === 'past' ? 'in the past' : 'coming up' ?>.</p>
</div>
</div>
