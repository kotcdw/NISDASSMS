<?php if (can('finance.manage')): ?>
<div class="actions"><details class="menu-btn" data-sidepanel><summary class="btn btn-primary">+ New ▾</summary><div>
    <a href="<?= e(url('finance/new', ['kind' => 'income'])) ?>">Income (money in)</a>
    <a href="<?= e(url('finance/new', ['kind' => 'expense'])) ?>">Expense (money out)</a>
    <a href="<?= e(url('finance/new', ['kind' => 'transfer'])) ?>">Transfer between accounts</a>
    <?php if (Family::ready()): ?><a href="<?= e(url('suppliers/bills/new')) ?>">Supplier bill</a><?php endif; ?>
</div></details></div>
<?php endif; ?>
