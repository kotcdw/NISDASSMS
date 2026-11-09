<?php $lbl = fn($s) => ucfirst(str_replace('_', ' ', (string) $s)); $active = array_filter($f, fn($v) => $v !== '' && $v !== 0); ?>
<div class="xgrid-box" data-xgrid="audit" data-xgrid-file="audit-log">
<div class="page-head">
    <div><h1>Audit log</h1></div>
    <div class="actions">
        <details class="menu-btn"<?= $active ? ' data-active' : '' ?>><summary class="btn<?= $active ? '' : ' btn-ghost' ?>">Filter<?= $active ? ' (' . count($active) . ')' : '' ?> ▾</summary><div style="min-width:17rem">
            <form method="get" action="<?= e(url('audit')) ?>" class="form" style="padding:.5rem .6rem;margin:0">
                <label>When<select name="period"><?php foreach (AuditController::PERIODS as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $f['period']) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
                <label>Who<select name="user"><option value="0">Anyone</option><option value="-1"<?= selected(-1, $f['user']) ?>>System / public</option><?php foreach ($people as $u): ?><option value="<?= (int) $u['id'] ?>"<?= selected($u['id'], $f['user']) ?>><?= e($u['name']) ?></option><?php endforeach; ?></select></label>
                <label>Action<select name="action"><option value="">Any action</option><?php foreach ($actions as $ac): ?><option value="<?= e($ac) ?>"<?= selected($ac, $f['action']) ?>><?= e($lbl($ac)) ?></option><?php endforeach; ?></select></label>
                <label>Record type<select name="entity"><option value="">Any record</option><?php foreach ($entities as $en): ?><option value="<?= e($en['entity']) ?>"<?= selected($en['entity'], $f['entity']) ?>><?= e($lbl($en['entity'])) ?></option><?php endforeach; ?></select></label>
                <label>Words in the details<input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Name, IP, words…"></label>
                <div class="gate-actions"><button class="btn btn-primary" type="submit">Apply</button><?php if ($active): ?><a class="btn btn-ghost" href="<?= e(url('audit')) ?>">Clear</a><?php endif; ?></div>
            </form>
        </div></details>
        <?= export_grid_menu() ?>
    </div>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['when' => ['When', 'date'], 'who' => ['Who'], 'action' => ['Action'], 'record' => ['Record'], 'no' => ['Record no.', 'num', 'num'], 'details' => ['Details'], 'ip' => ['IP']]) ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $det = AuditController::readable($r['details']); ?>
    <tr>
        <td data-c="when" data-s="<?= e($r['created_at']) ?>"><?= e(fmt_date($r['created_at'], 'd M Y H:i')) ?></td>
        <td data-c="who"><?= e($r['user_name'] ?? 'System / public') ?></td>
        <td data-c="action"><?= $r['action'] === 'access_denied' ? badge('Access denied', 'bad') : e($lbl($r['action'])) ?></td>
        <td data-c="record"><?= e($lbl($r['entity'])) ?></td>
        <td data-c="no" class="num"><?= $r['entity_id'] ? (int) $r['entity_id'] : '' ?></td>
        <td data-c="details" class="audit-details" title="<?= e($det) ?>"><?= e(mb_strimwidth($det, 0, 110, '…')) ?></td>
        <td data-c="ip"><?= e($r['ip']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>Nothing matches.</p>
</div>
</div>
<div class="list-pager"><?= pager_links($pager) ?></div>
