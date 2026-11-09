<?php require BASE_PATH . '/app/views/layouts/_public_url.php'; ?>
<?php
$manage = can('exams.manage');
$list = array_values(array_filter($classes, fn($c) => !empty($info[$c['id']])));
$q = ['term' => $term['id'] ?? null];
?>
<div class="xgrid-box" data-xgrid="report-cards" data-xgrid-file="report-cards">
<div class="page-head">
    <div><h1>Report cards</h1></div>
    <div class="actions">
        <form method="get" action="<?= e(url('reports')) ?>" class="list-pick" data-autosubmit>
            <select name="term" aria-label="Term">
                <?php foreach ($terms as $t): ?><option value="<?= (int) $t['id'] ?>"<?= selected($t['id'], $term['id'] ?? 0) ?>><?= e($t['year_name'] . ' · ' . $t['name']) ?></option><?php endforeach; ?>
            </select>
        </form>
        <?= export_grid_menu() ?>
    </div>
</div>
<?php if ($manage): foreach ($list as $c): if ($info[$c['id']]['released_at']) continue; ?>
<div id="release-<?= (int) $c['id'] ?>" class="card" hidden>
    <h2>Release <?= e($c['name']) ?> report cards</h2>
    <form method="post" action="<?= e(url('reports/class/' . $c['id'] . '/release', $q)) ?>" class="form">
        <?= csrf_field() ?>
        <label><input type="checkbox" name="sms" value="1" checked> SMS parents a private link to the report card</label>
        <label><input type="checkbox" name="force" value="1"> Release anyway if some subjects are not approved</label>
        <div class="gate-actions"><button class="btn btn-primary" type="submit">Release <?= e($c['name']) ?></button><button type="button" class="btn btn-ghost" data-help="release-<?= (int) $c['id'] ?>">Cancel</button></div>
    </form>
</div>
<?php endforeach; endif; ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['class' => ['Class'], 'teacher' => ['Class teacher'], 'pupils' => ['Students', 'num', 'num'], 'approved' => ['Subjects approved', 'num'], 'remarks' => ['Remarks', 'num', 'num'], 'status' => ['Status'], 'released' => ['Released on', 'date']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($list as $c): $i = $info[$c['id']]; $all = (int) $i['subjects'] && (int) $i['approved'] === (int) $i['subjects']; ?>
    <tr>
        <td data-c="class"><strong><?= e($c['name']) ?></strong></td>
        <td data-c="teacher"><?= e($c['teacher_name'] ?? '—') ?></td>
        <td data-c="pupils" class="num"><?= (int) $i['pupils'] ?></td>
        <td data-c="approved" data-s="<?= (int) $i['approved'] ?>"><?= $all ? badge('All ' . (int) $i['subjects'], 'good') : badge((int) $i['approved'] . ' of ' . (int) $i['subjects'], 'warn') ?></td>
        <td data-c="remarks" class="num" data-s="<?= (int) $i['remarks'] ?>"><?= (int) $i['remarks'] ?>/<?= (int) $i['pupils'] ?></td>
        <td data-c="status"><?= $i['released_at'] ? badge('Released', 'good') : badge('Not released') ?></td>
        <td data-c="released" data-s="<?= e((string) $i['released_at']) ?>"><?= $i['released_at'] ? e(fmt_date($i['released_at'], 'd M Y')) : '' ?></td>
        <td class="xg-act" style="white-space:nowrap">
            <?php $relMain = $manage && !$i['released_at']; ?>
            <?php if ($relMain): ?><button type="button" class="btn btn-sm btn-primary" data-help="release-<?= (int) $c['id'] ?>" aria-expanded="false">Release…</button>
            <?php else: ?><a class="btn btn-sm" href="<?= e(url('reports/class/' . $c['id'] . '/remarks', $q)) ?>">Remarks</a><?php endif; ?>
            <details class="rowmenu"><summary aria-label="More actions">⋯</summary><div>
                <a href="<?= e(url('reports/class/' . $c['id'] . '/broadsheet', $q)) ?>">Broadsheet</a>
                <?php if ($relMain): ?><a href="<?= e(url('reports/class/' . $c['id'] . '/remarks', $q)) ?>">Remarks</a><?php endif; ?>
                <a href="<?= e(url('reports/class/' . $c['id'] . '/print', $q)) ?>" target="_blank" rel="noopener">Print all</a>
                <?php if ($manage && $i['released_at']): ?>
                    <form method="post" action="<?= e(url('reports/class/' . $c['id'] . '/unrelease', $q)) ?>" data-confirm="Withdraw these reports? Parent links will stop working."><?= csrf_field() ?><button class="danger" type="submit">Withdraw</button></form>
                <?php endif; ?>
            </div></details>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $list ? ' hidden' : '' ?> data-xg-empty><?= $manage ? 'No classes to show for this term.' : 'You are not class teacher of any class.' ?></p>
</div>
</div>
<style>.xgrid td.xg-act:has(details[open]) { z-index: 3; }</style>
