<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('teachers')) ?>">Teachers</a> /</div>
    <h1>Appraisal criteria</h1></div>
    <div class="actions"><button class="btn btn-primary" type="submit" form="criteria-form">Save criteria</button></div>
</div>
<form method="post" action="<?= e(url('teachers/criteria')) ?>" class="form" id="criteria-form">
    <?= csrf_field() ?>
    <div class="card" style="padding:0"><div class="table-wrap"><table>
        <thead><tr><th style="width:70px">Order</th><th>Point</th><th>What it means</th><th>In use</th></tr></thead>
        <tbody>
        <?php foreach ($criteria as $c): ?>
            <tr>
                <td><input type="number" name="c[<?= (int) $c['id'] ?>][sort_order]" value="<?= (int) $c['sort_order'] ?>" aria-label="Order" style="margin:0"></td>
                <td><input name="c[<?= (int) $c['id'] ?>][name]" value="<?= e($c['name']) ?>" maxlength="120" aria-label="Point" style="margin:0"></td>
                <td><input name="c[<?= (int) $c['id'] ?>][description]" value="<?= e((string) $c['description']) ?>" maxlength="255" aria-label="What it means" style="margin:0"></td>
                <td><label style="margin:0"><input type="checkbox" name="c[<?= (int) $c['id'] ?>][is_active]" value="1"<?= (int) $c['is_active'] ? ' checked' : '' ?>> Yes</label></td>
            </tr>
        <?php endforeach; ?>
        <tr>
            <td class="muted">New</td>
            <td><input name="new_name" maxlength="120" placeholder="e.g. Use of ICT in teaching" aria-label="New point" style="margin:0"></td>
            <td><input name="new_description" maxlength="255" aria-label="New point meaning" style="margin:0"></td>
            <td></td>
        </tr>
        </tbody>
    </table></div></div>
</form>
