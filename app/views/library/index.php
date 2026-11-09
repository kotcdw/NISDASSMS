<?php $mg = can('library.manage'); $today = date('Y-m-d'); ?>
<div class="xgrid-box" data-xgrid="library-loans" data-xgrid-file="library-books-on-loan">
<div class="page-head">
    <div><h1>Library</h1></div>
    <div class="actions">
        <a class="btn btn-ghost" href="<?= e(url('library/report')) ?>" target="_blank">Print overdue list</a>
        <?php if ($mg && $stats['overdue']): ?><form method="post" action="<?= e(url('library/remind')) ?>" class="inline" data-confirm="Send an SMS reminder to the parents of students with overdue books?"><?= csrf_field() ?><button class="btn btn-ghost" type="submit">SMS overdue reminders</button></form><?php endif; ?>
        <?= export_grid_menu() ?>
        <?php if ($mg): ?><button type="button" class="btn btn-primary" data-help="lend-book" aria-expanded="false">+ Lend a book</button><?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<?php if ($mg) { $issueBook = null; $back = 'library'; $issueToggle = 'lend-book'; require __DIR__ . '/_issue.php'; } ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['book' => ['Book'], 'borrower' => ['Borrower'], 'adm' => ['Admission no.'], 'class' => ['Class'], 'lent' => ['Lent', 'date'], 'due' => ['Due', 'date'], 'status' => ['Status']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($loans as $l): $late = $l['status'] === 'out' && $l['due_on'] < $today; ?>
    <tr>
        <td data-c="book"><a href="<?= e(url('library/books/' . $l['book_id'])) ?>"><?= e($l['title']) ?></a></td>
        <td data-c="borrower"><?= e($l['borrower'] ?? '—') ?></td>
        <td data-c="adm"><?= $l['pupil_id'] ? e($l['admission_no']) : '' ?></td>
        <td data-c="class"><?= $l['pupil_id'] ? e($l['class_name'] ?? '') : 'Staff' ?></td>
        <td data-c="lent" data-s="<?= e($l['issued_on']) ?>"><?= e(fmt_date($l['issued_on'], 'd M')) ?></td>
        <td data-c="due" data-s="<?= e($l['due_on']) ?>"><?= e(fmt_date($l['due_on'], 'd M')) ?></td>
        <td data-c="status"><?= $late ? badge('Overdue', 'bad') : ($l['status'] === 'lost' ? badge('Lost') : badge('On loan', 'info')) ?></td>
        <?php require __DIR__ . '/_loan_actions.php'; ?>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $loans ? ' hidden' : '' ?> data-xg-empty>No books are out on loan.</p>
</div>
</div>
