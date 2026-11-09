<div class="xgrid-box" data-xgrid="appointment-types" data-xgrid-file="appointment-types">
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('appointments')) ?>">Appointments</a> /</div><h1>Appointment types</h1></div>
    <div class="actions"><?= export_grid_menu() ?><button type="button" class="btn btn-primary" data-help="new-type" aria-expanded="false">+ New type</button></div>
</div>
<div id="new-type" class="card" hidden>
    <h2>New type</h2>
    <form method="post" action="<?= e(url('appointments/types')) ?>" class="form"><?= csrf_field() ?>
        <div class="grid-4">
            <label class="required">Name<input name="name" required></label>
            <label>Minutes<input type="number" name="duration_min" value="20" min="5" max="180"></label>
            <label>Description<input name="description"></label>
            <label>Question for the parent (optional)<input name="question"></label>
        </div>
        <div class="gate-actions"><button class="btn btn-primary" type="submit">Save</button><button type="button" class="btn btn-ghost" data-help="new-type">Cancel</button></div>
    </form>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['type' => ['Type'], 'desc' => ['Description'], 'min' => ['Minutes', 'num', 'num'], 'staff' => ['Staff offering', 'num', 'num'], 'upcoming' => ['Coming up', 'num', 'num'], 'status' => ['Status']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($types as $t): ?>
    <tr<?= (int) $t['is_active'] ? '' : ' style="opacity:.55"' ?>>
        <td data-c="type"><strong><?= e($t['name']) ?></strong></td>
        <td data-c="desc"><?= e($t['description'] ?? '') ?></td>
        <td data-c="min" class="num"><?= (int) $t['duration_min'] ?></td>
        <td data-c="staff" class="num"><?= (int) $t['staff_n'] ?></td>
        <td data-c="upcoming" class="num"><?= (int) $t['upcoming'] ?></td>
        <td data-c="status"><?= (int) $t['is_active'] ? badge('In use', 'good') : badge('Not in use', 'neutral') ?></td>
        <td class="xg-act"><details><summary class="btn btn-sm btn-ghost">Edit</summary><form method="post" action="<?= e(url('appointments/types')) ?>" class="form" style="min-width:260px;text-align:left"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
            <label>Name<input name="name" value="<?= e($t['name']) ?>" required></label><label>Minutes<input type="number" name="duration_min" value="<?= (int) $t['duration_min'] ?>" min="5" max="180"></label>
            <label>Description<input name="description" value="<?= e($t['description'] ?? '') ?>"></label><label>Question for the parent<input name="question" value="<?= e($t['question'] ?? '') ?>"></label>
            <label style="font-weight:400"><input type="checkbox" name="is_active" value="1"<?= (int) $t['is_active'] ? ' checked' : '' ?>> In use</label><button class="btn btn-sm" type="submit">Save</button></form></details></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $types ? ' hidden' : '' ?> data-xg-empty>No appointment types yet.</p>
</div>
</div>
