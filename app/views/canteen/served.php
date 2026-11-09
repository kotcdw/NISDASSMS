<?php $ct = 'canteen/served'; ?>
<div class="page-head"><div><h1>Meals served</h1></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<form method="post" action="<?= e(url('canteen/served')) ?>" class="card" style="padding:0"><?= csrf_field() ?><input type="hidden" name="date" value="<?= e($date) ?>"><input type="hidden" name="meal" value="<?= e($meal) ?>">
    <div class="table-wrap"><table><tr><th><input type="checkbox" data-check-all="served[]" aria-label="All"></th><th>Student</th><th>Class</th><th>Plan</th><th>Register</th></tr>
    <?php foreach ($rows as $r): ?><tr><td><input type="checkbox" name="served[]" value="<?= (int) $r['id'] ?>"<?= $r['served'] ? ' checked' : '' ?> aria-label="<?= e($r['first_name']) ?>"></td>
        <td><?= e($r['last_name'] . ', ' . $r['first_name']) ?><?= trim((string) $r['allergies']) !== '' ? ' ' . badge('Allergy: ' . $r['allergies'], 'bad') : '' ?></td><td><?= e($r['class_name'] ?? '') ?></td><td><small><?= e($r['plan_name']) ?></small></td>
        <td><?= $r['att'] && $r['att'] !== 'present' ? badge(Attendance::STATUSES[$r['att']][0] ?? $r['att'], $r['att'] === 'late' ? 'warn' : 'bad') : '' ?></td></tr><?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="5" class="muted">Nobody is on a meal plan this term.</td></tr><?php endif; ?>
    </table></div><div style="padding:1rem"><button class="btn btn-primary" type="submit">Save</button> <span class="muted"><?= count(array_filter($rows, fn($r) => $r['served'])) ?> of <?= count($rows) ?> served</span></div>
</form>
