<div class="xgrid-box" data-xgrid="my-shop-orders" data-xgrid-file="my-shop-orders">
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('shop')) ?>">School shop</a> /</div><h1>My shop orders</h1></div>
    <div class="actions"><?= export_grid_menu() ?></div>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['order' => ['Order'], 'date' => ['Date', 'date'], 'for' => ['For'], 'items' => ['Items'], 'total' => ['Total (GH₵)', 'num', 'num'], 'status' => ['Status']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $o): [$sl, $st] = Shop::STATUS[$o['status']]; ?>
    <tr>
        <td data-c="order"><strong><?= e($o['order_no']) ?></strong></td>
        <td data-c="date" data-s="<?= e($o['created_at']) ?>"><?= e(fmt_date($o['created_at'], 'j M Y')) ?></td>
        <td data-c="for"><?= e($o['first_name']) ?></td>
        <td data-c="items"><?= e($o['items'] ?? '') ?></td>
        <td data-c="total" class="num"><?= e(number_format((float) $o['total'], 2)) ?></td>
        <td data-c="status"><?= badge($sl, $st) ?></td>
        <td class="xg-act" style="white-space:nowrap"><?php if ($o['status'] === 'pending_payment'): ?><a class="btn btn-sm btn-primary" href="<?= e(url('pay/' . $o['public_key'])) ?>">Pay now</a>
            <form method="post" action="<?= e(url('shop/orders/' . $o['id'] . '/cancel')) ?>" class="inline" data-confirm="Cancel this order?"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" type="submit">Cancel</button></form><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No orders yet.</p>
</div>
</div>
