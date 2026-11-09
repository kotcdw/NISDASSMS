<?php require __DIR__ . '/_tabs.php'; ?>
<div class="xgrid-box" data-xgrid="roles" data-xgrid-file="roles-and-security">
<div class="page-head">
    <div><h1>Roles &amp; security</h1></div>
    <div class="actions"><?= export_grid_menu() ?><a class="btn btn-primary" href="<?= e(url('security/role')) ?>">+ New role</a></div>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['role' => ['Role'], 'kind' => ['Type'], 'like' => ['Works like'], 'about' => ['Description'], 'people' => ['People', 'num', 'num'], 'off' => ['Switched off', 'num', 'num'], 'perms' => ['Permissions', 'num', 'num'], 'records' => ['Student records'], 'twostep' => ['2-step sign-in'], 'setup' => ['2-step set up', 'num', 'num'], 'idle' => ['Auto sign-out', 'num', 'num'], 'when' => ['When'], 'where' => ['Where']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($roles as $k => $r): $c = $counts[$k] ?? ['active' => 0, 'total' => 0, 'mfa' => 0]; $n = $k === 'super_admin' ? count(Permissions::all()) : count($grants[$k] ?? []); $offN = (int) $c['total'] - (int) $c['active']; ?>
    <tr<?= (int) $r['is_active'] ? '' : ' class="muted"' ?>>
        <td data-c="role"><strong><?= e($r['name']) ?></strong></td>
        <td data-c="kind"><?= (int) $r['is_system'] ? 'Built in' : badge('Custom', 'info') ?><?= (int) $r['is_active'] ? '' : '<span hidden> · </span> ' . badge('Off', 'bad') ?></td>
        <td data-c="like"><?= (int) $r['is_system'] ? '' : e(Auth::roleLabel($r['base_role'])) ?></td>
        <td data-c="about" class="audit-details"><?= e($r['description'] ?? '') ?></td>
        <td data-c="people" class="num"><?= (int) $c['active'] ?></td>
        <td data-c="off" class="num"><?= $offN > 0 ? $offN : '' ?></td>
        <td data-c="perms" class="num" data-s="<?= $n ?>"><?= $k === 'super_admin' ? 'All' : $n ?></td>
        <td data-c="records"><?= $r['kind'] !== 'staff' ? 'Own children / self' : ($r['data_scope'] === 'own' ? badge('Own classes', 'info') : 'All classes') ?></td>
        <td data-c="twostep"><?= (int) $r['require_2fa'] ? badge('Required', 'good') : 'Optional' ?></td>
        <td data-c="setup" class="num" data-s="<?= (int) $c['mfa'] ?>"><?= (int) $c['active'] ? (int) $c['mfa'] . ' of ' . (int) $c['active'] : '' ?></td>
        <td data-c="idle" class="num" data-s="<?= (int) $r['idle_minutes'] ?>"><?= (int) $r['idle_minutes'] ?> min</td>
        <td data-c="when"><?= $r['allowed_days'] || $r['allowed_from'] ? e(trim(implode(', ', array_map(fn($d) => Security::DAYS[(int) $d] ?? $d, array_filter(explode(',', (string) $r['allowed_days'])))) . ($r['allowed_from'] ? ' ' . substr($r['allowed_from'], 0, 5) . '–' . substr($r['allowed_to'], 0, 5) : ''))) : 'Any time' ?></td>
        <td data-c="where"><?= $r['allowed_networks'] ? 'School network only' : 'Anywhere' ?></td>
        <td class="xg-act"><a class="btn btn-sm" href="<?= e(url('security/role', ['key' => $k])) ?>"><?= $k === 'super_admin' ? 'View' : 'Edit' ?></a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $roles ? ' hidden' : '' ?> data-xg-empty>No roles yet.</p>
</div>
</div>
