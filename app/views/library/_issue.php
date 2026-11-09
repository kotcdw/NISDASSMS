<?php /* expects $issueBook (array|null), $staffUsers, $back; optional $issueToggle (id: the form starts hidden, opened by a button with data-help) */ $issueToggle = $issueToggle ?? ''; ?>
<form method="post" action="<?= e(url('library/issue')) ?>" class="card form<?= $issueToggle !== '' ? ' list-form' : '' ?>"<?= $issueToggle !== '' ? ' id="' . e($issueToggle) . '" hidden' : '' ?>>
    <?= csrf_field() ?>
    <input type="hidden" name="back" value="<?= e($back) ?>">
    <h2>Lend a book</h2>
    <?php if ($issueBook): ?>
        <input type="hidden" name="book_id" value="<?= (int) $issueBook['id'] ?>">
        <p class="hint"><?= e($issueBook['title']) ?></p>
    <?php else: ?>
        <label>Book number<input name="book_id" inputmode="numeric" required placeholder="Book no. (shown in the catalogue)"></label>
    <?php endif; ?>
    <label>Student's admission no.<input name="admission_no" placeholder="e.g. NIS/2026/0001"></label>
    <label>…or a staff member<select name="staff_user_id"<?= QuickAddController::attr('staff') ?>><option value="">—</option><?php foreach ($staffUsers as $u): ?><option value="<?= (int) $u['id'] ?>"><?= e($u['name']) ?></option><?php endforeach; ?></select></label>
    <label>Lend for (days)<input type="number" name="days" min="1" max="60" value="<?= (int) setting('library_loan_days', '14') ?>"></label>
    <?php if ($issueToggle !== ''): ?><div class="gate-actions"><button class="btn btn-primary" type="submit">Lend</button><button type="button" class="btn btn-ghost" data-help="<?= e($issueToggle) ?>">Cancel</button></div>
    <?php else: ?><button class="btn btn-primary" type="submit">Lend</button><?php endif; ?>
</form>
