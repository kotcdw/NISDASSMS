<?php $today = date('Y-m-d'); $soon = date('Y-m-d', strtotime('+' . Documents::REMIND_DAYS . ' days')); ?>
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url($back[0])) ?>"><?= e($back[1]) ?></a> /</div><h1>Files — <?= e($name) ?></h1></div>
</div>
<div class="cols">
<div>
    <div class="card" style="padding:0"><div class="table-wrap"><table>
        <tr><th>Document</th><th>Added</th><th>Expires</th><th></th></tr>
        <?php foreach ($docs as $d): $exp = $d['expires_on']; ?><tr>
            <td><a href="<?= e(url('documents/person/' . $d['id'] . '/file')) ?>" target="_blank"><strong><?= e(Documents::typeLabel($ownerType, $d['doc_type'])) ?></strong></a><?= $d['title'] ? '<br><small class="muted">' . e($d['title']) . '</small>' : '' ?></td>
            <td><?= e(fmt_date($d['created_at'], 'j M Y')) ?><br><small class="muted"><?= e($d['uploaded_name'] ?? '') ?></small></td>
            <td><?= $exp ? ($exp < $today ? badge('Expired ' . fmt_date($exp, 'j M Y'), 'bad') : ($exp <= $soon ? badge(fmt_date($exp, 'j M Y'), 'warn') : e(fmt_date($exp, 'j M Y')))) : '<span class="muted">—</span>' ?></td>
            <td><?php if ($canEdit): ?><form method="post" action="<?= e(url('documents/person/' . $d['id'] . '/delete')) ?>" data-confirm="Remove this file for good?"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" type="submit">Remove</button></form><?php endif; ?></td>
        </tr><?php endforeach; ?>
        <?php if (!$docs): ?><tr><td colspan="4" class="muted">No files yet.</td></tr><?php endif; ?>
    </table></div></div>
    <?php if ($fromApp): ?>
    <div class="card"><h2>From the admission application</h2>
        <?php foreach ($fromApp as $a): ?><p style="margin:0 0 .35rem"><a href="<?= e(url('admission/' . $a['app_id'] . '/documents/' . $a['id'])) ?>" target="_blank"><?= e(ucfirst(str_replace('_', ' ', $a['doc_type']))) ?></a> <small class="muted">(<?= e($a['ref_no']) ?>, <?= e(fmt_date($a['uploaded_at'])) ?>)</small></p><?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if ($ownerType === 'pupil'): $have = array_column($docs, 'doc_type'); $missing = array_diff(['birth_certificate', 'passport_photo', 'nhis_card'], $have); if ($missing): ?>
    <p class="alert alert-warning">Not yet on file: <?= e(implode(', ', array_map(fn($t) => Documents::PUPIL_TYPES[$t], $missing))) ?>.</p>
    <?php endif; endif; ?>
</div>
<div>
    <?php if ($canEdit): ?>
    <form method="post" action="<?= e(url($action)) ?>" enctype="multipart/form-data" class="card form"><?= csrf_field() ?><h2>Add a file</h2>
        <label class="required">Kind<select name="doc_type"><?php foreach ($types as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></label>
        <label>Note<input name="title" maxlength="160" placeholder="<?= $ownerType === 'pupil' ? 'e.g. Original seen by the office' : 'e.g. B.Ed certificate, UCC' ?>"></label>
        <label>Expires on<input type="date" name="expires_on"><small>NHIS card, licence, contract… you are reminded <?= Documents::REMIND_DAYS ?> days before.</small></label>
        <label class="required">File (PDF, JPG or PNG)<input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required></label>
        <button class="btn btn-primary" type="submit">Upload</button>
    </form>
    <?php elseif ($ownerType === 'staff'): ?>
    <div class="card"></div>
    <?php endif; ?>
</div>
</div>
