<div class="page-head">
    <div><h1>Teacher records</h1><p class="muted">Directory and qualification details only; payroll and banking data are not available here.</p></div>
    <div class="actions"><a class="btn" href="<?= e(url('users')) ?>">Manage staff accounts</a><a class="btn btn-primary" href="<?= e(url('teacher-records/create')) ?>">+ Add teacher record</a></div>
</div>
<div class="card" style="padding:0">
    <div class="table-wrap"><table>
        <thead><tr><th>Staff no.</th><th>Name</th><th>Position</th><th>Employment</th><th>Phone</th><th>Login</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $record): ?>
        <tr<?= (int) $record['is_active'] ? '' : ' class="muted"' ?>>
            <td><?= e($record['staff_no']) ?></td>
            <td><strong><?= e($record['name']) ?></strong></td>
            <td><?= e($record['position']) ?></td>
            <td><?= e(Payroll::EMPLOYMENT[$record['employment']] ?? $record['employment']) ?></td>
            <td><?= e($record['phone'] ?? '') ?></td>
            <td><?= e($record['email'] ?? 'No linked login') ?></td>
            <td><?= (int) $record['is_active'] ? 'Active' : 'Archived' ?></td>
            <td><div class="actions"><a class="btn btn-sm" href="<?= e(url('teacher-records/' . $record['id'] . '/edit')) ?>">Edit</a>
                <?php if ((int) $record['is_active']): ?><form method="post" action="<?= e(url('teacher-records/' . $record['id'] . '/archive')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" type="submit" data-confirm-click="Archive this teacher record? The login and class assignments will remain unchanged.">Archive</button></form><?php endif; ?>
            </div></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="8" class="muted">No teacher records yet.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>
