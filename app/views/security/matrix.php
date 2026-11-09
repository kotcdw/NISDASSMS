<div class="page-head"><div><h1>Permission matrix</h1></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<form method="post" action="<?= e(url('security/matrix')) ?>" data-confirm="Save the permission changes for all roles?">
    <?= csrf_field() ?>
    <?php $first = (string) (array_values(array_filter(array_keys($roles), fn($k) => $k !== 'super_admin'))[0] ?? array_key_first($roles)); ?>
    <div class="card ptabs" data-ptabs style="padding:0">
        <div class="ptab-bar" role="tablist" aria-label="Roles">
        <?php foreach ($roles as $k => $r): $sel = (string) $k === $first; ?>
            <button type="button" role="tab" id="pt-<?= e((string) $k) ?>" aria-controls="pp-<?= e((string) $k) ?>" aria-selected="<?= $sel ? 'true' : 'false' ?>"<?= $sel ? '' : ' tabindex="-1"' ?> data-ptab="<?= e((string) $k) ?>"<?= (int) $r['is_active'] ? '' : ' style="opacity:.6"' ?>><?= e($r['name']) ?></button>
        <?php endforeach; ?>
        </div>
        <?php foreach ($roles as $k => $r): ?>
        <div class="ptab-panel" role="tabpanel" id="pp-<?= e((string) $k) ?>" aria-labelledby="pt-<?= e((string) $k) ?>" data-ppanel="<?= e((string) $k) ?>"<?= (string) $k === $first ? '' : ' hidden' ?>>
            <p style="margin:0;padding:.7rem 1.2rem;border-bottom:1px solid var(--line)"><a href="<?= e(url('security/role', ['key' => $k])) ?>">Open the <?= e($r['name']) ?> role</a><?= (int) $r['is_active'] ? '' : ' <span class="muted">(not in use)</span>' ?><?= $k === 'super_admin' ? ' <span class="muted">— Super Admin always has every permission.</span>' : '' ?></p>
            <div class="table-wrap"><table class="matrix">
                <tbody>
                <?php foreach (Permissions::CATALOG as $module => $list): ?>
                    <tr><th colspan="2" style="text-align:left;background:var(--brand-soft, #eef3fb)"><?= e($module) ?></th></tr>
                    <?php foreach ($list as $p => [$label, $desc, $risk]): $lock = $k === 'super_admin' ? 'Super Admin always has every permission' : Permissions::lockReason($p, $r['kind'], (string) $k); $forced = $p === 'staff' && $r['kind'] === 'staff'; $on = $k === 'super_admin' || $forced || in_array($p, $grants[$k] ?? [], true); $cid = 'pm-' . $k . '-' . $p; ?>
                    <tr>
                        <td style="width:1%;text-align:center;padding-right:0"><input type="checkbox" id="<?= e($cid) ?>" <?= $k === 'super_admin' || $lock || $forced ? 'disabled' : 'name="perm[' . e($k) . '][]"' ?> value="<?= e($p) ?>"<?= checked($on && ($k === 'super_admin' || $forced || !$lock)) ?><?= $lock ? ' title="' . e($lock) . '"' : '' ?>></td>
                        <td><label for="<?= e($cid) ?>" style="margin:0;font-weight:400;cursor:pointer"><?= e($label) ?><?= $risk === 'high' ? ' <span style="color:var(--warn, #b45309)" title="Sensitive">●</span>' : '' ?><?php if ($desc): ?> <small class="muted"><?= e($desc) ?></small><?php endif; ?><?php if ($lock && $k !== 'super_admin'): ?> <small class="muted">(<?= e($lock) ?>)</small><?php endif; ?></label></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
        <?php endforeach; ?>
    </div>
    <p><button class="btn btn-primary" type="submit">Save all changes</button> <span class="hint">● = sensitive permission. Approve + pay requisitions, and prepare + approve payroll, cannot be given to the same role.</span></p>
</form>
