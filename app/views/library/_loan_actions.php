<?php /* Row actions for one loan ($l): Returned, and Renew / Lost in the ⋯ menu. Used by the Library page and a book's lending history. */ ?>
<td class="xg-act" style="white-space:nowrap"><?php if ($l['status'] === 'out' && can('library.manage')): ?>
    <form method="post" action="<?= e(url('library/loans/' . $l['id'] . '/return')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn-sm btn-good" type="submit">Returned</button></form>
    <details class="rowmenu"><summary aria-label="More actions">⋯</summary><div>
        <form method="post" action="<?= e(url('library/loans/' . $l['id'] . '/renew')) ?>"><?= csrf_field() ?><button type="submit">Renew</button></form>
        <form method="post" action="<?= e(url('library/loans/' . $l['id'] . '/lost')) ?>" data-confirm="Mark this copy as lost?"><?= csrf_field() ?><button class="danger" type="submit">Lost</button></form>
    </div></details>
<?php endif; ?></td>
