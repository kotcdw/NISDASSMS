<?php
/** Admission — one simple list: sort and export. Which columns show is chosen in Administration → School setup → List columns. */
$types = ['age' => 'num', 'received' => 'date'];
$show = array_flip(ListColumns::on('admission'));
$cols = array_intersect_key(ListColumns::LISTS['admission'][2], $show);
?>
<div class="xgrid-box" data-xgrid="admission" data-xgrid-file="applications">
<div class="page-head">
    <div><h1>Admission</h1></div>
    <div class="actions">
        <?php if (count($years) > 1): ?><form method="get" data-autosubmit style="margin:0"><select name="year" aria-label="Academic year"><?php foreach ($years as $y): ?><option value="<?= (int) $y['id'] ?>"<?= selected($y['id'], $yid) ?>><?= e($y['name']) ?></option><?php endforeach; ?></select></form><?php endif; ?>
        <a class="btn btn-ghost" href="<?= e(url('admission/merit')) ?>">Admission List</a>
        <?= export_grid_menu() ?>
        <?php if (can('admission.manage')): ?><a class="btn btn-primary" href="<?= e(url('admission/create')) ?>">+ Student Registration</a><?php endif; ?>
    </div>
</div>
    <div class="xg-scroll">
        <table class="xgrid">
            <thead><tr>
                <th class="xg-n" scope="col">#</th>
                <?php foreach ($cols as $k => [$t]): ?><th scope="col" data-c="<?= e($k) ?>" data-type="<?= $types[$k] ?? 'text' ?>"><button type="button" class="xg-sort" title="Sort"><?= e($t) ?><span class="xg-arrow" aria-hidden="true"></span></button></th><?php endforeach; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($apps as $i => $a): $href = e(url('admission/' . $a['id'])); $dob = (string) $a['date_of_birth']; ?>
                <tr>
                    <td class="xg-n"><?= $i + 1 ?></td>
                    <?php if (isset($show['ref'])): ?><td data-c="ref"><a href="<?= $href ?>"><strong><?= e($a['ref_no']) ?></strong></a></td><?php endif; ?>
                    <?php if (isset($show['name'])): ?><td data-c="name"><a href="<?= $href ?>"><?= e(full_name($a)) ?></a></td><?php endif; ?>
                    <?php if (isset($show['gender'])): ?><td data-c="gender"><?= e(ucfirst((string) $a['gender'])) ?></td><?php endif; ?>
                    <?php if (isset($show['class'])): ?><td data-c="class"><?= e($a['level_name']) ?></td><?php endif; ?>
                    <?php if (isset($show['age'])): ?><td data-c="age" class="num" data-s="<?= $dob ? (int) ((time() - strtotime($dob)) / 86400) : '' ?>"><?= e(age_from($a['date_of_birth'])) ?></td><?php endif; ?>
                    <?php if (isset($show['parent'])): ?><td data-c="parent"><?= e($a['guardian_name']) ?></td><?php endif; ?>
                    <?php if (isset($show['phone'])): ?><td data-c="phone"><?= e($a['guardian_phone']) ?></td><?php endif; ?>
                    <?php if (isset($show['source'])): ?><td data-c="source"><?= $a['source'] === 'online' ? 'Online' : 'Walk-in' ?></td><?php endif; ?>
                    <?php if (isset($show['stage'])): ?><td data-c="stage"><?= Admission::badge($a['stage']) ?></td><?php endif; ?>
                    <?php if (isset($show['received'])): ?><td data-c="received" data-s="<?= e((string) $a['created_at']) ?>"><?= e(fmt_date($a['created_at'])) ?></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="xg-empty muted"<?= $apps ? ' hidden' : '' ?> data-xg-empty><?= $apps ? 'No application matches your search.' : 'No applications yet.' ?></p>
    </div>
</div>
