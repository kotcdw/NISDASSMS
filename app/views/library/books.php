<?php require __DIR__ . '/_tabs.php'; ?>
<div class="xgrid-box" data-xgrid="library-books" data-xgrid-file="library-catalogue">
<div class="page-head">
    <div><h1>Library catalogue</h1></div>
    <div class="actions"><?= export_grid_menu() ?><?php if (can('library.manage')): ?><a class="btn btn-primary" href="<?= e(url('library/books/create')) ?>">+ Add a book</a><?php endif; ?></div>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['no' => ['No.', 'num', 'num'], 'title' => ['Title'], 'author' => ['Author'], 'category' => ['Category'], 'level' => ['Level'], 'shelf' => ['Shelf'], 'copies' => ['Copies', 'num', 'num'], 'avail' => ['Available', 'num', 'num']]) ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $b): $avail = (int) $b['copies'] - (int) $b['copies_lost'] - (int) $b['out_now']; ?>
    <tr>
        <td data-c="no" class="num"><?= (int) $b['id'] ?></td>
        <td data-c="title"><a href="<?= e(url('library/books/' . $b['id'])) ?>"><strong><?= e($b['title']) ?></strong></a></td>
        <td data-c="author"><?= e($b['author'] ?? '') ?></td>
        <td data-c="category"><?= e($b['category'] ?? '') ?></td>
        <td data-c="level"><?= e($b['level_band'] ?? '') ?></td>
        <td data-c="shelf"><?= e($b['shelf'] ?? '') ?></td>
        <td data-c="copies" class="num"><?= (int) $b['copies'] - (int) $b['copies_lost'] ?></td>
        <td data-c="avail" class="num" data-s="<?= $avail ?>"><?= $avail > 0 ? badge((string) $avail, 'good') : badge('All out', 'warn') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No books found.</p>
</div>
</div>
<div class="list-pager"><?= pager_links($pager) ?></div>
