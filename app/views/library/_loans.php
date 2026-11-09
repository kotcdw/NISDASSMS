<div class="table-wrap"><table>
    <tr><th>Book</th><th>Borrower</th><th>Lent</th><th>Due</th><th></th></tr>
    <?php foreach ($loans as $l): $late = $l['status'] === 'out' && $l['due_on'] < date('Y-m-d'); ?>
    <tr>
        <td><a href="<?= e(url('library/books/' . $l['book_id'])) ?>"><?= e($l['title']) ?></a></td>
        <td><?= e($l['borrower'] ?? '—') ?><br><small class="muted"><?= $l['pupil_id'] ? e($l['admission_no']) . ' · ' . e($l['class_name'] ?? '') : 'Staff' ?></small></td>
        <td><?= e(fmt_date($l['issued_on'], 'd M')) ?></td>
        <td><?= e(fmt_date($l['due_on'], 'd M')) ?> <?= $late ? badge('Overdue', 'bad') : '' ?><?= $l['status'] === 'returned' ? '<br><small class="muted">Returned ' . e(fmt_date($l['returned_on'], 'd M')) . '</small>' : '' ?><?= $l['status'] === 'lost' ? ' ' . badge('Lost') : '' ?></td>
        <?php require __DIR__ . '/_loan_actions.php'; ?>
    </tr>
    <?php endforeach; ?>
    <?php if (!$loans): ?><tr><td colspan="5" class="muted">Nothing here.</td></tr><?php endif; ?>
</table></div>
