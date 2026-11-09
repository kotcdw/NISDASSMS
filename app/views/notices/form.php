<?php require BASE_PATH . '/app/views/layouts/_public_url.php'; ?>
<div class="page-center">
<?php $v = fn($k, $d = '') => old($k, $n[$k] ?? $d); ?>
<div class="page-head"><div><p class="breadcrumb"><a href="<?= e(url('notices')) ?>">Notice board</a></p><h1><?= e($title) ?></h1></div></div>
<form method="post" action="<?= e(url($n ? 'notices/' . $n['id'] : 'notices')) ?>" enctype="multipart/form-data" class="card form">
    <?= csrf_field() ?>
    <label>Title<input name="title" required maxlength="150" value="<?= e($v('title')) ?>" placeholder="e.g. Mid-term break and PTA meeting"></label>
    <div class="grid-2">
        <label>Kind<select name="category"><?php foreach (Notices::CATEGORIES as $k => [$l]): ?><option value="<?= e($k) ?>"<?= selected($v('category', 'notice'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
        <label>Who should see it<select name="audience"><?php foreach (Notices::audiences() as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($v('audience', 'all'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
    </div>
    <label>Message<textarea name="body" rows="10" required maxlength="20000"><?= e($v('body')) ?></textarea></label>
    <div class="grid-2">
        <label>Publish on<input type="datetime-local" name="publish_at" value="<?= e(str_replace(' ', 'T', substr((string) $v('publish_at', date('Y-m-d H:i')), 0, 16))) ?>"></label>
        <label>Remove from the board after (optional)<input type="date" name="expires_on" value="<?= e($v('expires_on')) ?>"></label>
    </div>
    <label>Attachment (PDF, JPG or PNG)<input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png"></label>
    <?php if ($n && $n['attachment']): ?><label style="font-weight:400"><input type="checkbox" name="remove_attachment" value="1"> Remove the current attachment (<?= e($n['attachment_name']) ?>)</label><?php endif; ?>
    <label style="font-weight:400"><input type="checkbox" name="requires_ack" value="1"<?= checked((bool) $v('requires_ack', 0)) ?>> Ask parents to confirm they have read it (circulars)</label>
    <label style="font-weight:400"><input type="checkbox" name="is_pinned" value="1"<?= checked((bool) $v('is_pinned', 0)) ?>> Pin to the top</label>
    <?php if (Notices::loginReady()): ?><label style="font-weight:400"><input type="checkbox" name="on_login" value="1"<?= checked((bool) $v('on_login', 0)) ?>> Also show on the sign-in page — anyone can read it there, without signing in (and download the attachment)</label><?php endif; ?>
    <?php if (NoticeController::hasExtras()): ?><label>Priority<select name="priority"><?php foreach (['normal' => 'Normal', 'important' => 'Important', 'urgent' => 'Urgent'] as $pk => $pl): ?><option value="<?= $pk ?>"<?= selected($v('priority', 'normal'), $pk) ?>><?= $pl ?></option><?php endforeach; ?></select><small>Circulars get a number automatically (e.g. NIS/CIR/<?= date('Y') ?>/001).</small></label><?php endif; ?>
    <?php if (!$n || !$n['sms_sent_at']): ?><label style="font-weight:400"><input type="checkbox" name="send_sms" value="1"> Also send a short SMS alert with a link (one per family; costs SMS credit)</label><?php endif; ?>
    <button class="btn btn-primary" type="submit">Publish</button>
</form>
</div>
