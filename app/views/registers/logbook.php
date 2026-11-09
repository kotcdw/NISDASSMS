<?php
$e = $edit ?? null; $v = fn($k, $d = '') => $e ? (string) $e[$k] : (string) old($k, $d);
$openForm = $e || old('title') !== '';
$dates = $period === 'custom' ? ['from' => $from, 'to' => $to] : ['period' => $period];
?>
<div class="xgrid-box" data-xgrid="logbook" data-xgrid-file="head-teachers-log-book">
<div class="page-head">
    <div><h1>Head teacher's log book</h1></div>
    <div class="actions">
        <form method="get" action="<?= e(url('logbook')) ?>" class="list-pick" data-autosubmit>
            <?php foreach ($dates as $dk => $dv): ?><input type="hidden" name="<?= e($dk) ?>" value="<?= e($dv) ?>"><?php endforeach; ?>
            <select name="category" aria-label="Kind of entry"><option value="">All kinds</option><?php foreach (Registers::LOG_CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($cat, $k) ?>><?= e($l) ?></option><?php endforeach; ?></select>
        </form>
        <a class="btn btn-ghost" href="<?= e(url('logbook', ['from' => $from, 'to' => $to, 'category' => $cat, 'q' => $q, 'print' => 1])) ?>" target="_blank">Print</a>
        <?= export_grid_menu() ?>
        <button type="button" class="btn btn-primary" data-help="new-entry" aria-expanded="<?= $openForm ? 'true' : 'false' ?>">+ New entry</button>
    </div>
</div>
<?php $route = 'logbook'; $keep = ['category' => $cat]; require __DIR__ . '/_range.php'; ?>
<form id="new-entry" method="post" action="<?= e(url('logbook')) ?>" class="card form list-form"<?= $openForm ? '' : ' hidden' ?>>
    <?= csrf_field() ?>
    <h2><?= $e ? 'Correct the entry' : 'New entry' ?></h2>
    <?php if ($e): ?><input type="hidden" name="id" value="<?= (int) $e['id'] ?>"><?php endif; ?>
    <div class="grid-2">
        <label class="required">Date<input type="date" name="entry_date" max="<?= e(date('Y-m-d')) ?>" value="<?= e($v('entry_date', date('Y-m-d'))) ?>" required></label>
        <label class="required">Kind<select name="category"><?php foreach (Registers::LOG_CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($v('category', 'event'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
    </div>
    <label class="required">Heading<input name="title" maxlength="150" required value="<?= e($v('title')) ?>" placeholder="e.g. Circuit supervisor's visit"></label>
    <label class="required">What happened<textarea name="body" rows="8" maxlength="10000" required placeholder="Who, what, what was decided or done"><?= e($v('body')) ?></textarea></label>
    <div class="gate-actions"><button class="btn btn-primary" type="submit"><?= $e ? 'Save correction' : 'Add to the log book' ?></button><?php if ($e): ?><a class="btn btn-ghost" href="<?= e(url('logbook')) ?>">Cancel</a><?php else: ?><button type="button" class="btn btn-ghost" data-help="new-entry">Cancel</button><?php endif; ?></div>
</form>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['date' => ['Date', 'date'], 'kind' => ['Kind'], 'title' => ['Heading'], 'body' => ['What happened'], 'by' => ['Written by'], 'written' => ['Written on', 'date'], 'corrected' => ['Corrected', 'date']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
    <tr>
        <td data-c="date" data-s="<?= e($r['entry_date']) ?>"><?= e(date('D j M Y', strtotime($r['entry_date']))) ?></td>
        <td data-c="kind"><?= badge(Registers::LOG_CATEGORIES[$r['category']] ?? $r['category'], 'info') ?></td>
        <td data-c="title"><strong><?= e($r['title']) ?></strong></td>
        <td data-c="body" class="audit-details"><?= nl2br(e($r['body'])) ?></td>
        <td data-c="by"><?= e($r['author'] ?? '—') ?></td>
        <td data-c="written" data-s="<?= e($r['created_at']) ?>"><?= e(date('j M Y H:i', strtotime($r['created_at']))) ?></td>
        <td data-c="corrected" data-s="<?= e((string) $r['updated_at']) ?>"><?= $r['updated_at'] ? e(date('j M H:i', strtotime($r['updated_at']))) : '' ?></td>
        <td class="xg-act"><?php if ((int) $r['created_by'] === (int) Auth::id() && substr($r['created_at'], 0, 10) === date('Y-m-d')): ?><a class="btn btn-sm btn-ghost" href="<?= e(url('logbook', ['edit' => $r['id']])) ?>">Correct</a><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No entries in this period.</p>
</div>
</div>
