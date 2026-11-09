<div class="xgrid-box" data-xgrid="documents-expiring" data-xgrid-file="expiring-documents">
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('documents')) ?>">Documents</a> /</div><h1>Expiring documents</h1></div>
    <div class="actions"><?= export_grid_menu() ?></div>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['expires' => ['Expires', 'date'], 'doc' => ['Document'], 'whose' => ['Whose'], 'kind' => ['Kind']]) ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $href = $r['kind'] === 'school' ? url('documents') : url('documents/' . ($r['kind'] === 'pupil' ? 'pupils/' : 'staff/') . $r['owner_id']); ?>
    <tr>
        <td data-c="expires" data-s="<?= e($r['expires_on']) ?>"><?= $r['expires_on'] < date('Y-m-d') ? badge('Expired ' . fmt_date($r['expires_on'], 'j M Y'), 'bad') : e(fmt_date($r['expires_on'], 'D j M Y')) ?></td>
        <td data-c="doc"><a href="<?= e($href) ?>"><?= e($r['kind'] === 'school' ? $r['title'] : Documents::typeLabel($r['kind'], $r['type']) . ($r['title'] ? ' — ' . $r['title'] : '')) ?></a></td>
        <td data-c="whose"><?= $r['kind'] === 'school' ? 'School' : e($r['owner_name']) ?></td>
        <td data-c="kind"><?= $r['kind'] === 'school' ? 'School document' : ($r['kind'] === 'pupil' ? 'Student' : 'Staff') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>Nothing expiring.</p>
</div>
</div>
