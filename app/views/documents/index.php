<?php
$mg = can('documents.manage'); $openForm = old('title') !== ''; $soonDay = date('Y-m-d', strtotime('+' . Documents::REMIND_DAYS . ' days'));
$tabs = ['0' => ['Current', url('documents', ['category' => $cat ?: null])], '1' => ['Archived', url('documents', ['category' => $cat ?: null, 'archived' => 1])]];
?>
<?php if ($expiring): ?>
<div class="alert alert-warning"><strong><?= count($expiring) ?> document(s) expire within <?= Documents::REMIND_DAYS ?> days or have expired.</strong> <a href="<?= e(url('documents/expiring')) ?>">See the list →</a></div>
<?php endif; ?>
<div class="xgrid-box" data-xgrid="documents" data-xgrid-file="school-documents">
<div class="page-head">
    <div><h1>Documents</h1></div>
    <div class="actions">
        <form method="get" action="<?= e(url('documents')) ?>" class="list-pick" data-autosubmit>
            <?php if ($archived): ?><input type="hidden" name="archived" value="1"><?php endif; ?>
            <select name="category" aria-label="Kind"><option value="">All kinds</option><?php foreach (Documents::CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($cat, $k) ?>><?= e($l) ?></option><?php endforeach; ?></select>
        </form>
        <?php if (can('documents.staff')): ?><a class="btn btn-ghost" href="<?= e(url('documents/staff')) ?>">Staff files</a><?php endif; ?>
        <a class="btn btn-ghost" href="<?= e(url('documents/my')) ?>">My files</a>
        <?= export_grid_menu() ?>
        <?php if ($mg): ?><button type="button" class="btn btn-primary" data-help="new-document" aria-expanded="<?= $openForm ? 'true' : 'false' ?>">+ Add a document</button><?php endif; ?>
    </div>
</div>
<?php if ($mg): ?><?= card_tabs($tabs, $archived ? '1' : '0', 4, 'Documents to show') ?><?php endif; ?>
<?php if ($mg): ?>
<form id="new-document" method="post" action="<?= e(url('documents')) ?>" enctype="multipart/form-data" class="card form list-form"<?= $openForm ? '' : ' hidden' ?>><?= csrf_field() ?><h2>Add a document</h2>
    <div class="grid-2">
        <label class="required">Title<input name="title" maxlength="160" required value="<?= e(old('title')) ?>"></label>
        <label class="required">Kind<select name="category"><?php foreach (Documents::CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old('category'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
        <label>Reference number<input name="reference_no" maxlength="60" value="<?= e(old('reference_no')) ?>"></label>
        <label>Who can read it<select name="audience"><option value="all_staff">All staff</option><option value="management"<?= selected(old('audience'), 'management') ?>>Management only</option></select></label>
    </div>
    <label>Short description<textarea name="description" rows="2" maxlength="500"><?= e(old('description')) ?></textarea></label>
    <div class="grid-2">
        <label>Expires on<input type="date" name="expires_on" value="<?= e(old('expires_on')) ?>"><small>For licences and certificates: you are reminded <?= Documents::REMIND_DAYS ?> days before.</small></label>
        <label class="required">File (PDF, JPG or PNG)<input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required></label>
    </div>
    <div class="gate-actions"><button class="btn btn-primary" type="submit">Upload</button><button type="button" class="btn btn-ghost" data-help="new-document">Cancel</button></div>
</form>
<?php endif; ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['doc' => ['Document'], 'ref' => ['Ref. no.'], 'about' => ['Description'], 'kind' => ['Kind'], 'who' => ['Who can read'], 'added' => ['Added', 'date'], 'by' => ['Added by'], 'expires' => ['Expires', 'date']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $d): $exp = $d['expires_on']; ?>
    <tr>
        <td data-c="doc"><a href="<?= e(url('documents/' . $d['id'] . '/file')) ?>" target="_blank"><strong><?= e($d['title']) ?></strong></a></td>
        <td data-c="ref"><?= e($d['reference_no'] ?? '') ?></td>
        <td data-c="about"><?= $d['description'] ? e(mb_strimwidth($d['description'], 0, 90, '…')) : '' ?></td>
        <td data-c="kind"><?= e(Documents::CATEGORIES[$d['category']]) ?></td>
        <td data-c="who"><?= $d['audience'] === 'management' ? badge('Management only', 'warn') : 'All staff' ?></td>
        <td data-c="added" data-s="<?= e($d['created_at']) ?>"><?= e(fmt_date($d['created_at'], 'j M Y')) ?></td>
        <td data-c="by"><?= e($d['uploaded_name'] ?? '') ?></td>
        <td data-c="expires" data-s="<?= e((string) $exp) ?>"><?= $exp ? ($exp < date('Y-m-d') ? badge('Expired ' . fmt_date($exp, 'j M Y'), 'bad') : ($exp <= $soonDay ? badge(fmt_date($exp, 'j M Y'), 'warn') : e(fmt_date($exp, 'j M Y')))) : '<span class="muted">—</span>' ?></td>
        <td class="xg-act"><?php if ($mg): ?><form method="post" action="<?= e(url('documents/' . $d['id'] . ($archived ? '/restore' : '/archive'))) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" type="submit"><?= $archived ? 'Restore' : 'Archive' ?></button></form><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No documents<?= $q !== '' || $cat !== '' ? ' match your choice' : ' yet' ?>.</p>
</div>
</div>
<div class="list-pager"><?= pager_links($pager) ?></div>
