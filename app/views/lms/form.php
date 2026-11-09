<?php $cur = $m ? ($m['class_subject_id'] ? 'cs:' . $m['class_subject_id'] : 'c:' . $m['class_id']) : ''; $kind = old('kind', $m['kind'] ?? 'note'); ?>
<div class="page-center">
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('lms')) ?>">LMS</a> /</div><h1><?= $m ? 'Edit material' : 'New learning material' ?></h1></div>
</div>
<?php if (!$targets): ?><div class="card"><p class="muted">You have no classes or subjects assigned yet. Ask the coordinator to assign you on the timetable.</p></div><?php else: ?>
<form method="post" action="<?= e(url($m ? 'lms/' . $m['id'] : 'lms')) ?>" enctype="multipart/form-data" class="form card" style="max-width:820px"><?= csrf_field() ?>
    <div class="grid-2">
        <label class="required">For<select name="target" required><option value="">— choose —</option><?php foreach ($targets as $t): ?><option value="<?= e($t['key']) ?>"<?= selected(old('target', $cur), $t['key']) ?>><?= e($t['label']) ?></option><?php endforeach; ?></select></label>
        <label class="required">Kind<select name="kind" required><?php foreach (Learning::KINDS as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($kind, $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
    </div>
    <label class="required">Title<input name="title" maxlength="150" required value="<?= e(old('title', $m['title'] ?? '')) ?>" placeholder="e.g. Fractions — adding with the same denominator"></label>
    <label>Curriculum strand (optional)<select name="strand_id"><option value="">—</option><?php foreach ($strands as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected(old('strand_id', $m['strand_id'] ?? ''), $s['id']) ?>><?= e($s['subject_name'] . ': ' . $s['name']) ?></option><?php endforeach; ?></select></label>
    <label>Lesson note / instructions<textarea name="body" rows="10" maxlength="20000"><?= e(old('body', $m['body'] ?? '')) ?></textarea><small>Required for a lesson note. For a video, link or file, write what students should do.</small></label>
    <label>Web address (for a video or link)<input name="url" maxlength="500" value="<?= e(old('url', $m['url'] ?? '')) ?>" placeholder="https://www.youtube.com/watch?v=…"><small>YouTube videos play inside the page.</small></label>
    <label>Worksheet / file (PDF, JPG or PNG)<input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png"><?php if (!empty($m['attachment'])): ?><small>Current file: <?= e($m['attachment_name']) ?> (choose a new one to replace it)</small><?php endif; ?></label>
    <label>Show it<select name="status"><option value="published">Now — tell students and parents</option><option value="draft"<?= selected(old('status', $m['status'] ?? ''), 'draft') ?>>Not yet (draft)</option></select></label>
    <button class="btn btn-primary" type="submit">Save</button>
</form>
<?php endif; ?>
</div>
