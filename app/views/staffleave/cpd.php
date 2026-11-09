<?php
$num = fn($v) => $v !== null ? e(rtrim(rtrim((string) $v, '0'), '.')) : '';
$recOpen = old('title') !== '';
$cols = ['from' => ['Date', 'date'], 'to' => ['Until', 'date']] + ($all ? ['name' => ['Staff']] : []) + ['title' => ['Training'], 'kind' => ['Kind'], 'provider' => ['Provider'], 'hours' => ['Hours', 'num', 'num'], 'points' => ['Points', 'num', 'num'], 'cert' => ['Certificate']];
?>
<div class="xgrid-box" data-xgrid="cpd" data-xgrid-file="staff-training">
<div class="page-head">
    <div><h1>Staff training (CPD)</h1></div>
    <div class="actions">
        <?php if ($all): ?>
        <form method="get" action="<?= e(url('cpd')) ?>" class="list-pick" data-autosubmit>
            <?php if (isset($_GET['period']) && $period !== 'custom'): ?><input type="hidden" name="period" value="<?= e($period) ?>"><?php endif; ?>
            <select name="user" aria-label="Staff member"><option value="">Everyone</option><?php foreach ($staff as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected($uid, $s['id']) ?>><?= e($s['name']) ?></option><?php endforeach; ?></select>
        </form>
        <?php if (!$uid): ?><button type="button" class="btn btn-ghost" data-help="cpd-year" aria-expanded="false">This year per staff member</button><?php endif; ?>
        <?php endif; ?>
        <?= export_grid_menu() ?>
        <button type="button" class="btn btn-primary" data-help="new-cpd" aria-expanded="<?= $recOpen ? 'true' : 'false' ?>">+ Record training</button>
    </div>
</div>
<?php $route = 'cpd'; $keep = ['user' => $uid]; require __DIR__ . '/../registers/_range.php'; ?>
<div id="new-cpd" class="card"<?= $recOpen ? '' : ' hidden' ?>>
    <h2>Record training</h2>
    <form method="post" action="<?= e(url('cpd')) ?>" class="form" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <?php if ($all): ?><label>Staff member<select name="user_id"><option value="">Me</option><?php foreach ($staff as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected(old('user_id', $uid ?: ''), $s['id']) ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></label><?php endif; ?>
        <label class="required">Training<input name="title" maxlength="150" required value="<?= e(old('title')) ?>" placeholder="e.g. Standards-based curriculum INSET"></label>
        <div class="grid-2">
            <label class="required">Kind<select name="kind"><?php foreach (Registers::CPD_KINDS as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old('kind', 'workshop'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
            <label>Provider<input name="provider" maxlength="150" value="<?= e(old('provider')) ?>" placeholder="e.g. GES, NTC, NaCCA"></label>
            <label class="required">Start<input type="date" name="start_date" required value="<?= e(old('start_date')) ?>"></label>
            <label>End<input type="date" name="end_date" value="<?= e(old('end_date')) ?>"></label>
            <label>Hours<input type="number" name="hours" min="0" max="999" step="0.5" value="<?= e(old('hours')) ?>"></label>
            <label>CPD points<input type="number" name="points" min="0" max="999" step="0.5" value="<?= e(old('points')) ?>"></label>
        </div>
        <label>Certificate (PDF or photo)<input type="file" name="certificate" accept="application/pdf,image/jpeg,image/png"></label>
        <div class="gate-actions"><button class="btn btn-primary" type="submit">Save</button><button type="button" class="btn btn-ghost" data-help="new-cpd">Cancel</button></div>
    </form>
</div>
<?php if ($all && !$uid): ?>
<div id="cpd-year" class="card" hidden style="padding:0">
    <h2 style="padding:1rem 1rem 0;margin:0">This year per staff member</h2>
    <div class="table-wrap"><table><tr><th>Staff</th><th class="num">Trainings</th><th class="num">Hours</th><th class="num">Points</th></tr>
    <?php foreach ($totals as $t): ?><tr><td><a href="<?= e(url('cpd', ['user' => $t['id']])) ?>"><?= e($t['name']) ?></a></td><td class="num"><?= (int) $t['n'] ?></td><td class="num"><?= $num($t['hours']) ?: '0' ?></td><td class="num"><?= $num($t['points']) ?: '0' ?></td></tr><?php endforeach; ?>
    </table></div>
</div>
<?php endif; ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th($cols) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $c): $till = $c['end_date'] && $c['end_date'] !== $c['start_date']; ?>
    <tr>
        <td data-c="from" data-s="<?= e($c['start_date']) ?>" style="white-space:nowrap"><?= e(fmt_date($c['start_date'])) ?></td>
        <td data-c="to" data-s="<?= $till ? e($c['end_date']) : '' ?>" style="white-space:nowrap"><?= $till ? e(fmt_date($c['end_date'])) : '' ?></td>
        <?php if ($all): ?><td data-c="name"><?= e($c['name']) ?></td><?php endif; ?>
        <td data-c="title"><strong><?= e($c['title']) ?></strong></td>
        <td data-c="kind"><?= e(Registers::CPD_KINDS[$c['kind']] ?? '') ?></td>
        <td data-c="provider"><?= e((string) $c['provider']) ?></td>
        <td data-c="hours" class="num"><?= $num($c['hours']) ?></td>
        <td data-c="points" class="num"><?= $num($c['points']) ?></td>
        <td data-c="cert"><?= $c['certificate'] ? '<a href="' . e(url('cpd/' . $c['id'] . '/file')) . '" target="_blank">Certificate</a>' : '' ?></td>
        <td class="xg-act"><form method="post" action="<?= e(url('cpd/' . $c['id'] . '/delete')) ?>" data-confirm="Remove this training record?"><?= csrf_field() ?><button class="btn btn-sm btn-ghost">Remove</button></form></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No training recorded<?= $period === 'all' ? ' yet' : ' in this period' ?>.</p>
</div>
</div>
