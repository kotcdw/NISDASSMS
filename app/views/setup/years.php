<?php
$curYear = null;
foreach ($years as $y) { if ($y['is_current']) { $curYear = $y; } }
$showId = (int) ($_GET['year'] ?? ($curYear['id'] ?? ($years[0]['id'] ?? 0)));
$y = null;
foreach ($years as $yy) { if ((int) $yy['id'] === $showId) { $y = $yy; } }
$y = $y ?? $curYear ?? ($years[0] ?? null);
?>
<?php $addOpen = !empty($GLOBALS['__old']) || !empty($GLOBALS['__errors']); ?>
<div class="page-head">
    <div><h1>Academic years &amp; terms</h1></div>
    <div class="actions">
        <?php if (count($years) > 1): ?><form method="get" action="<?= e(url('setup/years')) ?>" class="list-pick" data-autosubmit><select name="year" aria-label="Academic year"><?php foreach ($years as $yy): ?><option value="<?= (int) $yy['id'] ?>"<?= selected($yy['id'], $y['id'] ?? 0) ?>><?= e($yy['name']) ?><?= $yy['is_current'] ? ' (current)' : '' ?></option><?php endforeach; ?></select></form><?php endif; ?>
        <button type="button" class="btn btn-primary" data-help="new-year" aria-expanded="<?= $addOpen ? 'true' : 'false' ?>">+ Add a year</button>
    </div>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>

<div class="card form-center" id="new-year"<?= $addOpen ? '' : ' hidden' ?>>
    <h2>Add academic year</h2>
    <form method="post" action="<?= e(url('setup/years')) ?>" class="form">
        <?= csrf_field() ?>
        <div class="grid-3">
            <label class="required">Name <input name="name" placeholder="2027/2028" value="<?= e(old('name')) ?>"><?= field_error('name') ?></label>
            <label class="required">Starts <input type="date" name="start_date" value="<?= e(old('start_date')) ?>"><?= field_error('start_date') ?></label>
            <label class="required">Ends <input type="date" name="end_date" value="<?= e(old('end_date')) ?>"><?= field_error('end_date') ?></label>
        </div>
        <p class="muted" style="margin:0 0 .8rem;font-size:.9rem">Three terms are created with the year. Adjust their dates afterwards.</p>
        <div class="gate-actions"><button class="btn btn-primary" type="submit">Save</button><button type="button" class="btn btn-ghost" data-help="new-year">Cancel</button></div>
    </form>
</div>

<?php if ($y): ?>
<div class="card page-center" style="padding:0">
    <div class="year-head">
        <div><h2 style="margin:0"><?= e($y['name']) ?> <?= $y['is_current'] ? badge('Current', 'good') : '' ?></h2>
        <small class="muted"><?= e(fmt_date($y['start_date'])) ?> – <?= e(fmt_date($y['end_date'])) ?></small></div>
        <?php if (!$y['is_current']): ?>
        <form method="post" action="<?= e(url('setup/years/' . $y['id'] . '/current')) ?>" data-confirm="Make <?= e($y['name']) ?> the current academic year?">
            <?= csrf_field() ?><button class="btn btn-sm" type="submit">Make current</button>
        </form>
        <?php endif; ?>
    </div>
    <div class="table-wrap"><table class="terms-table">
        <thead><tr><th>Term</th><th>Reopens</th><th>Vacates</th><th>Status</th><th class="xg-act"></th></tr></thead>
        <tbody>
        <?php foreach ($terms[$y['id']] ?? [] as $t): $fid = 'term-' . (int) $t['id']; ?>
        <tr>
            <td><?= e($t['name']) ?></td>
            <td><?= e(fmt_date($t['start_date'])) ?></td>
            <td><?= e(fmt_date($t['end_date'])) ?></td>
            <td><?= $t['is_current'] ? badge('Current term', 'good') : '' ?></td>
            <td class="xg-act" style="white-space:nowrap">
                <button type="button" class="btn btn-sm" data-help="edit-<?= $fid ?>" aria-expanded="false">Edit</button>
                <?php if (!$t['is_current']): ?>
                <details class="rowmenu"><summary aria-label="More actions">⋯</summary><div>
                    <form method="post" action="<?= e(url('setup/terms/' . $t['id'] . '/current')) ?>" style="margin:0"><?= csrf_field() ?><button type="submit">Set as current term</button></form>
                </div></details>
                <?php endif; ?>
            </td>
        </tr>
        <tr id="edit-<?= $fid ?>" class="xg-edit" hidden><td colspan="5">
            <form method="post" action="<?= e(url('setup/terms/' . $t['id'])) ?>" class="form" style="margin:0"><?= csrf_field() ?>
                <div class="grid-3">
                    <label class="required">Term name <input name="name" value="<?= e($t['name']) ?>" required></label>
                    <label class="required">Reopens <input type="date" name="start_date" value="<?= e($t['start_date']) ?>"></label>
                    <label class="required">Vacates <input type="date" name="end_date" value="<?= e($t['end_date']) ?>"></label>
                </div>
                <div class="gate-actions"><button class="btn btn-primary" type="submit">Save</button><button type="button" class="btn btn-ghost" data-help="edit-<?= $fid ?>">Cancel</button></div>
            </form>
        </td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
<?php endif; ?>
