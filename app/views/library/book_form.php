<?php $v = fn($k, $d = '') => old($k, $b[$k] ?? $d); $manage = can('library.manage'); ?>
<div class="page-head"><div><p class="breadcrumb"><a href="<?= e(url('library/books')) ?>">Library catalogue</a></p><h1><?= e($title) ?></h1><?= $b ? '' : '' ?></div></div>
<div class="cols">
<div>
<?php if ($manage): ?>
<form method="post" action="<?= e(url($b ? 'library/books/' . $b['id'] : 'library/books')) ?>" class="card form">
    <?= csrf_field() ?>
    <label class="required">Title<input name="title" required maxlength="200" value="<?= e($v('title')) ?>"><?= field_error('title') ?></label>
    <div class="grid-2">
        <label>Author<input name="author" maxlength="150" value="<?= e($v('author')) ?>"></label>
        <label>Publisher<input name="publisher" maxlength="120" value="<?= e($v('publisher')) ?>"></label>
        <label>ISBN<input name="isbn" maxlength="20" value="<?= e($v('isbn')) ?>"></label>
        <label>Category<select name="category"<?= QuickAddController::attr('library_category') ?>><option value="">—</option><?php foreach (LibraryController::categories() as $c): ?><option<?= selected($v('category'), $c) ?>><?= e($c) ?></option><?php endforeach; ?></select><?= field_error('category') ?></label>
        <label>For (level)<input name="level_band" maxlength="30" value="<?= e($v('level_band')) ?>" placeholder="e.g. Basic 1–3, JHS"></label>
        <label>Shelf<input name="shelf" maxlength="30" value="<?= e($v('shelf')) ?>" placeholder="e.g. A2"></label>
        <label class="required">Copies<input type="number" name="copies" min="1" max="2000" value="<?= e($v('copies', '1')) ?>"><?= field_error('copies') ?></label>
    </div>
    <?php if ($b): ?>
        <?= (int) $b['copies_lost'] ? '<p class="hint">' . (int) $b['copies_lost'] . ' copy/copies recorded as lost (not counted as available).</p>' : '' ?>
        <label style="font-weight:400"><input type="checkbox" name="withdrawn" value="1"> Remove this book from the catalogue (worn out / given away)</label>
        <button class="btn btn-primary" type="submit">Save</button>
    <?php else: ?>
        <div style="display:flex;gap:.5rem"><button class="btn btn-primary" type="submit">Save</button><button class="btn" type="submit" name="another" value="1">Save and add another</button></div>
    <?php endif; ?>
</form>
<?php elseif ($b): ?>
<div class="card"><p><?= e($b['author'] ?? '') ?></p><p class="muted"><?= e(implode(' · ', array_filter([$b['category'], $b['level_band'], $b['shelf'] ? 'Shelf ' . $b['shelf'] : null]))) ?></p></div>
<?php endif; ?>
<?php if ($b): $loans = $history; ?>
<div class="card" style="padding:0"><h2 style="padding:1rem 1rem 0">Lending history</h2><?php require __DIR__ . '/_loans.php'; ?></div>
<?php endif; ?>
</div>
<div><?php if ($b && $manage && (int) $b['is_active']) { $issueBook = $b; $staffUsers = db()->all("SELECT id, name FROM users WHERE is_active = 1 AND role NOT IN ('parent','pupil') ORDER BY name"); $back = 'library/books/' . $b['id']; require __DIR__ . '/_issue.php'; } ?></div>
</div>
