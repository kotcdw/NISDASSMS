<?php $openForm = old('visitor_name') !== ''; ?>
<?php if ($inside): ?><div class="alert alert-info"><?= (int) $inside ?> visitor<?= $inside > 1 ? 's are' : ' is' ?> on the school grounds now (signed in, not yet out).</div><?php endif; ?>
<div class="xgrid-box" data-xgrid="visitors" data-xgrid-file="visitors-book">
<div class="page-head">
    <div><h1>Visitors' book</h1></div>
    <div class="actions">
        <?= export_grid_menu() ?>
        <details class="menu-btn"><summary class="btn">More ▾</summary><div>
            <a href="<?= e(url('visitors', ['from' => $from, 'to' => $to, 'q' => $q, 'csv' => 1])) ?>">Full visitors' book with who recorded each visit (Excel)</a>
        </div></details>
        <button type="button" class="btn btn-primary" data-help="new-visitor" aria-expanded="<?= $openForm ? 'true' : 'false' ?>">+ Sign a visitor in</button>
    </div>
</div>
<?php $route = 'visitors'; require __DIR__ . '/_range.php'; ?>
<form id="new-visitor" method="post" action="<?= e(url('visitors')) ?>" class="card form list-form"<?= $openForm ? '' : ' hidden' ?>>
    <?= csrf_field() ?>
    <h2>Sign a visitor in</h2>
    <label class="required">Name<input name="visitor_name" maxlength="150" required value="<?= e(old('visitor_name')) ?>"></label>
    <div class="grid-2">
        <label>Phone<input name="phone" inputmode="tel" maxlength="20" value="<?= e(old('phone')) ?>"></label>
        <label>Organisation<input name="organisation" maxlength="150" value="<?= e(old('organisation')) ?>" placeholder="e.g. GES District Office"></label>
        <label class="required">Purpose<select name="purpose"><?php foreach (Registers::VISIT_PURPOSES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old('purpose', 'parent'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
        <label>To see<input name="to_see" maxlength="150" value="<?= e(old('to_see')) ?>" placeholder="e.g. Head teacher"></label>
        <label>Student's admission no.<input name="admission_no" maxlength="30" value="<?= e(old('admission_no')) ?>" placeholder="If visiting a student"></label>
        <label>Visitor badge no.<input name="badge_no" maxlength="20" value="<?= e(old('badge_no')) ?>"></label>
    </div>
    <label>Details<input name="details" maxlength="255" value="<?= e(old('details')) ?>"></label>
    <div class="gate-actions"><button class="btn btn-primary" type="submit">Sign in now</button><button type="button" class="btn btn-ghost" data-help="new-visitor">Cancel</button></div>
</form>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['in' => ['Time in', 'date'], 'visitor' => ['Visitor'], 'org' => ['Organisation'], 'phone' => ['Phone'], 'badge' => ['Badge no.'], 'purpose' => ['Purpose'], 'details' => ['Details'], 'student' => ['Student'], 'see' => ['To see'], 'out' => ['Time out']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
    <tr>
        <td data-c="in" data-s="<?= e($r['time_in']) ?>"><?= e(date('j M', strtotime($r['time_in']))) ?> <strong><?= e(substr($r['time_in'], 11, 5)) ?></strong></td>
        <td data-c="visitor"><strong><?= e($r['visitor_name']) ?></strong></td>
        <td data-c="org"><?= e($r['organisation'] ?? '') ?></td>
        <td data-c="phone"><?= e($r['phone'] ?? '') ?></td>
        <td data-c="badge"><?= e($r['badge_no'] ?? '') ?></td>
        <td data-c="purpose"><?= e(Registers::VISIT_PURPOSES[$r['purpose']] ?? $r['purpose']) ?></td>
        <td data-c="details"><?= e($r['details'] ?? '') ?></td>
        <td data-c="student"><?= $r['first_name'] ? e($r['first_name'] . ' ' . $r['last_name']) : '' ?></td>
        <td data-c="see"><?= e($r['to_see'] ?? '') ?></td>
        <td data-c="out" data-s="<?= e((string) $r['time_out']) ?>"><?= $r['time_out'] ? e(substr($r['time_out'], 11, 5)) : badge('Still inside', 'warn') ?></td>
        <td class="xg-act"><?php if (!$r['time_out']): ?><form method="post" action="<?= e(url('visitors/' . $r['id'] . '/out')) ?>"><?= csrf_field() ?><button class="btn btn-sm" type="submit">Sign out</button></form><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No visitors in this period.</p>
</div>
</div>
