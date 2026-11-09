<?php $mg = can('permits.manage'); $e = $edit ?? null; $v = fn($k) => $e ? (string) ($e[$k] ?? '') : (string) old($k); $today = date('Y-m-d'); $soon = date('Y-m-d', strtotime('+60 days')); $openForm = $e || old('name') !== ''; ?>
<div class="xgrid-box" data-xgrid="permits" data-xgrid-file="licences-and-permits">
<div class="page-head">
    <div><h1>Licences &amp; permits</h1></div>
    <div class="actions"><?= export_grid_menu() ?><?php if ($mg): ?><button type="button" class="btn btn-primary" data-help="new-permit" aria-expanded="<?= $openForm ? 'true' : 'false' ?>">+ Add a licence or permit</button><?php endif; ?></div>
</div>
<?php if ($mg): ?>
<div id="new-permit" class="card list-form"<?= $openForm ? '' : ' hidden' ?>>
<form method="post" action="<?= e(url('permits')) ?>" class="form" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <h2><?= $e ? 'Edit / renew' : 'Add a licence or permit' ?></h2>
    <?php if ($e): ?><input type="hidden" name="id" value="<?= (int) $e['id'] ?>"><?php endif; ?>
    <label class="required">Name<input name="name" maxlength="150" required list="permit-names" value="<?= e($v('name')) ?>"></label>
    <datalist id="permit-names"><?php foreach (Registers::PERMIT_SUGGESTIONS as $s): ?><option value="<?= e($s) ?>"><?php endforeach; ?></datalist>
    <div class="grid-2">
        <label>Issued by<input name="authority" maxlength="150" value="<?= e($v('authority')) ?>" placeholder="e.g. NaSIA"></label>
        <label>Reference no.<input name="ref_no" maxlength="80" value="<?= e($v('ref_no')) ?>"></label>
        <label>Issued on<input type="date" name="issued_on" value="<?= e($v('issued_on')) ?>"></label>
        <label>Expires on<input type="date" name="expires_on" value="<?= e($v('expires_on')) ?>"></label>
    </div>
    <label>Notes<input name="notes" maxlength="500" value="<?= e($v('notes')) ?>"></label>
    <label>Document (PDF or photo)<input type="file" name="document" accept="application/pdf,image/jpeg,image/png"></label>
    <div class="gate-actions"><button class="btn btn-primary" type="submit">Save</button><?php if ($e): ?><a class="btn btn-ghost" href="<?= e(url('permits')) ?>">Cancel</a><?php else: ?><button type="button" class="btn btn-ghost" data-help="new-permit">Cancel</button><?php endif; ?></div>
</form>
<?php if ($e): ?><form method="post" action="<?= e(url('permits/' . $e['id'] . '/delete')) ?>" data-confirm="Remove <?= e($e['name']) ?> from the register?" style="margin-top:.6rem"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">Remove from the register</button></form><?php endif; ?>
</div>
<?php endif; ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['name' => ['Licence / permit'], 'by' => ['Issued by'], 'ref' => ['Ref no.'], 'expires' => ['Expires', 'date'], 'status' => ['Status'], 'notes' => ['Notes'], 'doc' => ['Document']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
    <tr>
        <td data-c="name"><strong><?= e($r['name']) ?></strong></td>
        <td data-c="by"><?= e($r['authority'] ?? '') ?></td>
        <td data-c="ref"><?= e($r['ref_no'] ?? '') ?></td>
        <td data-c="expires" data-s="<?= e((string) $r['expires_on']) ?>"><?= $r['expires_on'] ? e(fmt_date($r['expires_on'])) : '<span class="muted">No expiry</span>' ?></td>
        <td data-c="status"><?= !$r['expires_on'] ? '' : ($r['expires_on'] < $today ? badge('Expired', 'bad') : ($r['expires_on'] <= $soon ? badge('Renew soon', 'warn') : badge('Valid', 'good'))) ?></td>
        <td data-c="notes"><?= e($r['notes'] ?? '') ?></td>
        <td data-c="doc"><?= $r['document'] ? '<a href="' . e(url('permits/' . $r['id'] . '/file')) . '" target="_blank">Document</a>' : '' ?></td>
        <td class="xg-act"><?php if ($mg): ?><a class="btn btn-sm" href="<?= e(url('permits', ['edit' => $r['id']])) ?>">Edit / renew</a><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>Nothing recorded yet. Start with the NaSIA licence.</p>
</div>
</div>
