<?php require __DIR__ . '/_tabs.php'; ?>
<?php $isN = $kind === 'nhis'; $tabs = ['' => [$isN ? 'Missing or expiring' : 'Health needs', url('health/report', array_filter(['kind' => $kind ?: null, 'class' => $classId ?: null]))], '1' => ['Every student', url('health/report', array_filter(['kind' => $kind ?: null, 'class' => $classId ?: null, 'all' => 1]))]]; ?>
<div class="xgrid-box" data-xgrid="health-<?= e($kind) ?>" data-xgrid-file="<?= $isN ? 'nhis-status' : 'class-health-register' ?>">
<div class="page-head">
    <div><h1><?= $isN ? 'NHIS' : 'Class health register' ?></h1></div>
    <div class="actions">
        <form method="get" action="<?= e(url('health/report')) ?>" class="list-pick" data-autosubmit>
            <input type="hidden" name="kind" value="<?= e($kind) ?>"><?php if (!empty($_GET['all'])): ?><input type="hidden" name="all" value="1"><?php endif; ?>
            <select name="class" aria-label="Class"><option value="">All classes</option><?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($classId, $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
        </form>
        <a class="btn btn-ghost" href="<?= e(url('health/report', array_filter(['kind' => $kind, 'class' => $classId ?: null, 'all' => $_GET['all'] ?? null, 'print' => 1]))) ?>" target="_blank" rel="noopener">Print</a>
        <?= export_grid_menu() ?>
    </div>
</div>
<?= card_tabs($tabs, (string) ($_GET['all'] ?? ''), 2, 'Students to show') ?>
    <div class="xg-scroll"><table class="xgrid">
    <?php if ($kind === 'nhis'): ?>
    <thead><tr><?= list_th(['class' => ['Class'], 'student' => ['Student'], 'nhis' => ['NHIS no.'], 'exp' => ['Expiry', 'date']]) ?><th class="xg-act"></th></tr></thead><tbody>
    <?php foreach ($rows as $r): $exp = $r['nhis_expiry']; ?>
    <tr><td data-c="class"><?= e($r['class_name']) ?></td><td data-c="student"><a href="<?= e(url('health/pupils/' . $r['id'])) ?>"><?= e($r['first_name'] . ' ' . $r['last_name']) ?></a></td><td data-c="nhis"><?= e($r['nhis_no'] ?: '—') ?></td>
        <td data-c="exp" data-s="<?= e((string) $exp) ?>"><?= $exp ? e(fmt_date($exp)) . '<span hidden> · </span> ' . ($exp < date('Y-m-d') ? badge('Expired', 'bad') : ($exp < date('Y-m-d', strtotime('+30 days')) ? badge('Expires soon', 'warn') : '')) : badge('Not recorded') ?></td>
        <td class="xg-act"><a class="btn btn-sm" href="<?= e(url('health/pupils/' . $r['id'])) ?>">Update</a></td></tr>
    <?php endforeach; ?>
    <?php else: ?>
    <thead><tr><?= list_th(['class' => ['Class'], 'student' => ['Student'], 'allergy' => ['Allergies'], 'cond' => ['Conditions / medication']]) ?><th class="xg-act"></th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
    <tr><td data-c="class"><?= e($r['class_name']) ?></td><td data-c="student"><a href="<?= e(url('health/pupils/' . $r['id'])) ?>"><?= e($r['first_name'] . ' ' . $r['last_name']) ?></a><?= in_array($r['genotype'], ['SS', 'SC'], true) ? '<span hidden> · </span> ' . badge($r['genotype'], 'warn') : '' ?></td>
        <td data-c="allergy"><?= e($r['allergies'] ?? '') ?></td><td data-c="cond" class="audit-details"><?= e(trim(($r['conditions'] ?? '') . ($r['medication'] ? ' · ' . $r['medication'] : ''), ' ·')) ?></td>
        <td class="xg-act"><a class="btn btn-sm" href="<?= e(url('health/pupils/' . $r['id'])) ?>">Open</a></td></tr>
    <?php endforeach; ?>
    <?php endif; ?>
    </tbody></table>
    <p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>Nobody on this list.</p>
    </div>
</div>
