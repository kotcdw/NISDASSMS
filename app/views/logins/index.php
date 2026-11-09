<?php require BASE_PATH . '/app/views/layouts/_public_url.php'; ?>
<?php $isP = $tab === 'parents'; $noun = $isP ? 'parents' : 'students';
$tabs = ['parents' => ['Parents', url('logins', ['tab' => 'parents', 'class' => $classId ?: null])], 'pupils' => ['Students', url('logins', ['tab' => 'pupils', 'class' => $classId ?: null])]]; ?>
<?php if (!empty($_GET['slips'])): ?><div class="alert alert-success">Sign-in slips are ready. <a class="btn btn-sm btn-primary" href="<?= e(url('logins/slips')) ?>" target="_blank" rel="noopener">Print slips</a> (they can be opened only once).</div><?php endif; ?>
<form method="post" action="<?= e(url($isP ? 'logins/parents' : 'logins/pupils')) ?>" class="xgrid-box" data-xgrid="logins-<?= e($tab) ?>" data-xgrid-file="<?= e($noun) ?>-logins" data-confirm="Create sign-ins for the ticked <?= $noun ?>? Existing ones get a new password." id="logins-form">
    <?= csrf_field() ?><input type="hidden" name="class" value="<?= (int) $classId ?>">
<div class="page-head">
    <div><h1>Parent &amp; student logins</h1></div>
    <div class="actions">
        <?= export_grid_menu() ?>
        <button type="button" class="btn btn-primary" data-help="logins-bar" aria-expanded="<?= $classId ? 'true' : 'false' ?>">Select</button>
    </div>
</div>
<?= card_tabs($tabs, $isP ? 'parents' : 'pupils', 4, 'Logins to show') ?>
<div id="logins-bar" class="sel-bar"<?= $classId ? '' : ' hidden' ?>>
    <span class="list-pick"><select form="logins-class" name="class" aria-label="Class" data-autosubmit><option value="0">All classes</option><?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($classId, $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></span>
    <span class="muted">Tick the <?= $noun ?> below, then</span>
    <?php if ($isP): ?><label style="font-weight:400;margin:0;display:inline-flex;gap:.35rem;align-items:center"><input type="checkbox" name="sms" value="1" checked> SMS the details</label><?php endif; ?>
    <button class="btn btn-primary btn-sm" type="submit">Create / reset sign-ins</button>
    <button type="button" class="btn btn-ghost btn-sm" data-help="logins-bar">Cancel</button>
</div>
<style>
#logins-form .sel-col { display: none; }
#logins-form:has(#logins-bar:not([hidden])) .sel-col { display: table-cell; }
#logins-bar { display: flex; flex-wrap: wrap; gap: .6rem; align-items: center; padding: .7rem 1rem; background: var(--brand-soft, #eef3fb); border-bottom: 1px solid var(--line); }
#logins-bar[hidden] { display: none; }
</style>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><th class="sel-col" style="width:2.2rem"><input type="checkbox" data-check-all="ids[]" aria-label="All"></th>
        <?= $isP ? list_th(['name' => ['Parent / guardian'], 'signin' => ['Sign-in (phone)'], 'children' => ['Children']]) : list_th(['name' => ['Student'], 'signin' => ['Sign-in (admission no.)'], 'class' => ['Class']]) ?>
        <?= list_th(['status' => ['Status'], 'last' => ['Last sign-in', 'date']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
    <tr>
        <td class="sel-col"><input type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>"<?= $r['user_id'] ? '' : ' data-new' ?> aria-label="Select <?= e($isP ? $r['name'] : trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? $r['name'] ?? ''))) ?>"></td>
        <?php if ($isP): ?>
            <td data-c="name"><?= e($r['name']) ?></td><td data-c="signin"><?= e($r['phone']) ?></td><td data-c="children"><?= e($r['children']) ?></td>
        <?php else: ?>
            <td data-c="name"><?= e(full_name($r)) ?></td><td data-c="signin"><code><?= e(strtolower($r['admission_no'])) ?></code></td><td data-c="class"><?= e($r['class_name']) ?></td>
        <?php endif; ?>
        <td data-c="status"><?php if (!$r['user_id']): ?><?= badge('No login') ?>
            <?php elseif (!(int) $r['is_active']): ?><?= badge('Disabled', 'bad') ?>
            <?php elseif ($r['last_login_at']): ?><?= badge('Active', 'good') ?>
            <?php else: ?><?= badge('Not used yet', 'warn') ?><?php endif; ?></td>
        <td data-c="last" data-s="<?= e((string) ($r['last_login_at'] ?? '')) ?>"><?= !empty($r['last_login_at']) ? e(date('d M', strtotime($r['last_login_at']))) : '' ?></td>
        <td class="xg-act"><?php if ($r['user_id']): ?><details class="rowmenu"><summary aria-label="Actions">⋯</summary><div><button type="submit" form="toggle-<?= (int) $r['user_id'] ?>"<?= (int) $r['is_active'] ? ' class="danger"' : '' ?>><?= (int) $r['is_active'] ? 'Disable sign-in' : 'Enable sign-in' ?></button></div></details><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No active <?= $isP ? 'parents' : 'students' ?> in this class.</p>
</div>
</form>
<form method="get" action="<?= e(url('logins')) ?>" id="logins-class"><input type="hidden" name="tab" value="<?= e($tab) ?>"></form>
<?php foreach ($rows as $r): if (!$r['user_id']) continue; ?>
<form method="post" action="<?= e(url('logins/users/' . $r['user_id'] . '/toggle')) ?>" id="toggle-<?= (int) $r['user_id'] ?>" class="inline"><?= csrf_field() ?></form>
<?php endforeach; ?>
