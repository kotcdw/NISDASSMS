<?php $total = array_sum(array_map(fn($r) => (int) $r['planned'], $rows)); ?>
<div class="page-head">
    <div><h1>Lessons per week</h1></div>
    <div class="actions">
        <form method="get" action="<?= e(url('timetable/lessons')) ?>" class="list-pick" data-autosubmit>
            <select name="class" aria-label="Class"><?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($c['id'], $class['id'] ?? 0) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
        </form>
    </div>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<?php if ($class): ?>
<form method="post" action="<?= e(url('timetable/lessons/' . $class['id'])) ?>">
    <?= csrf_field() ?>
    <div class="card" style="padding:0">
        <div class="table-wrap"><table>
            <thead><tr><th>Subject</th><th>Teacher</th><th class="num">Periods / week</th><th class="num">On time table</th><th class="xg-act"></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): $k = (int) $r['id']; ?>
            <tr>
                <td><?= e($r['name']) ?></td><td><?= e($r['teacher_name'] ?? '—') ?></td>
                <td class="num"><?= (int) $r['planned'] ?></td>
                <td class="num"><?= (int) $r['scheduled'] ?></td>
                <td class="xg-act"><button type="button" class="btn btn-sm" data-help="edit-<?= $k ?>" aria-expanded="false">Edit</button></td>
            </tr>
            <tr id="edit-<?= $k ?>" class="xg-edit" hidden>
                <td colspan="5">
                    <label style="margin:0 0 .6rem;max-width:16rem">Periods per week for <?= e($r['name']) ?><input type="number" name="ppw[<?= $k ?>]" min="0" max="20" value="<?= (int) $r['planned'] ?>"></label>
                    <div class="gate-actions"><button class="btn btn-primary" type="submit">Save</button><button type="button" class="btn btn-ghost" data-help="edit-<?= $k ?>">Cancel</button></div>
                </td>
            </tr>
            <?php endforeach; ?>
            <tr><td colspan="2"><strong>Total</strong></td><td class="num"><strong><?= $total ?></strong> of <?= (int) $capacity ?></td><td></td><td></td></tr>
            </tbody>
        </table></div>
    </div>
    <?php if ($total > $capacity): ?><div class="alert alert-error">More lessons than available periods (<?= (int) $capacity ?>). Reduce some subjects or add lesson periods in the bell schedule.</div><?php endif; ?>
    <label style="margin:.5rem 0"><input type="checkbox" name="apply_level" value="1"> Copy these numbers to the other classes of the same level (e.g. all Basic 4 streams)</label>
    <button class="btn btn-primary" type="submit">Save</button>
</form>
<?php endif; ?>
