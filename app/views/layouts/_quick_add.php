<?php
/** Forms used by "+ Add new …" in dropdowns (only for what this person may add). */
$qaCan = fn(string $k) => can(QuickAddController::KINDS[$k][0]);
?>
<?php if ($qaCan('staff')): ?>
<template id="qa-tpl-staff">
    <label>Full name<input name="name" required maxlength="150" placeholder="e.g. Mrs. Abena Owusu"></label>
    <label>Email (used to sign in)<input type="email" name="email" required maxlength="190"></label>
    <div class="grid-2">
        <label>Role<select name="role"><?php foreach (QuickAddController::STAFF_ROLES as $r): ?><option value="<?= e($r) ?>"<?= $r === 'class_teacher' ? ' selected' : '' ?>><?= e(Auth::roleLabel($r)) ?></option><?php endforeach; ?></select></label>
        <label>Phone (optional)<input name="phone" maxlength="20" placeholder="0244123456"></label>
    </div>
    
</template>
<?php endif; ?>
<?php if ($qaCan('class')): ?>
<template id="qa-tpl-class">
    <div class="grid-2">
        <label>Class name<input name="name" required maxlength="40" placeholder="e.g. Basic 4B"></label>
        <label>Level<select name="level_id"><?php foreach (db()->all('SELECT id, name FROM levels ORDER BY sort_order') as $l): ?><option value="<?= (int) $l['id'] ?>"><?= e($l['name']) ?></option><?php endforeach; ?></select></label>
    </div>
    <label>Seats<input type="number" name="capacity" min="1" max="200" value="40"></label>
</template>
<?php endif; ?>
<?php if ($qaCan('subject')): ?>
<template id="qa-tpl-subject">
    <div class="grid-2">
        <label>Subject name<input name="name" required maxlength="80" placeholder="e.g. Asante Twi"></label>
        <label>Short code (optional)<input name="code" maxlength="12" placeholder="e.g. TWI"></label>
    </div>
    <p class="hint" style="margin:0 0 .3rem">Taught in:</p>
    <div style="display:flex;gap:1rem;flex-wrap:wrap">
        <?php foreach (['creche' => 'Creche', 'kg' => 'KG', 'primary' => 'Basic 1–6', 'jhs' => 'JHS'] as $k => $l): ?><label style="font-weight:400"><input type="checkbox" name="applies_to[]" value="<?= $k ?>"<?= in_array($k, ['primary', 'jhs'], true) ? ' checked' : '' ?>> <?= $l ?></label><?php endforeach; ?>
    </div>
</template>
<?php endif; ?>
<?php if ($qaCan('fee_item')): ?>
<template id="qa-tpl-fee_item">
    <label>Fee item name<input name="name" required maxlength="80" placeholder="e.g. ICT levy"></label>
    <label>Charged<select name="frequency"><option value="termly">Every term</option><option value="yearly">Once a year (Term 1)</option><option value="once">Once, on admission</option></select></label>
    <label style="font-weight:400"><input type="checkbox" name="discountable" value="1"> Sibling / staff discounts apply to this item</label>
</template>
<?php endif; ?>
<?php if ($qaCan('fin_account')): ?>
<template id="qa-tpl-fin_account">
    <label>Account name<input name="name" required maxlength="80" placeholder="e.g. Fidelity Bank — PTA account"></label>
    <div class="grid-2">
        <label>Type<select name="account_kind"><?php foreach (Finance::ACCOUNT_KINDS as $k => $l): ?><option value="<?= e($k) ?>"<?= $k === 'bank' ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
        <label>Account / wallet number (optional)<input name="account_no" maxlength="60"></label>
    </div>
</template>
<template id="qa-tpl-fin_category">
    <label>Category name<input name="name" required maxlength="80" placeholder="e.g. Internet subscription"></label>
    <label>Type<select name="category_kind"><option value="expense">Expense (money out)</option><option value="income">Income (money in)</option></select></label>
</template>
<?php endif; ?>
<?php if ($qaCan('library_category')): ?>
<template id="qa-tpl-library_category">
    <label>Category name<input name="name" required maxlength="60" placeholder="e.g. Ghanaian history"></label>
</template>
<?php endif; ?>
