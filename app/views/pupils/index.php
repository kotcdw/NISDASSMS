<?php
/** Students — one simple list: sort and export. Columns are chosen in Administration → School setup → List columns. */
$types = ['age' => 'num', 'dob' => 'date', 'admitted' => 'date'];
$show = array_flip(ListColumns::on('students'));
$cols = array_intersect_key(ListColumns::LISTS['students'][2], $show);
$statusLabels = ['active' => 'Active', 'withdrawn' => 'Withdrawn', 'transferred' => 'Transferred', 'graduated' => 'Graduated'];
?>
<div class="xgrid-box" data-xgrid="students" data-xgrid-file="students">
<div class="page-head">
    <div><h1>Students</h1></div>
    <div class="actions">
        <?php if (can('imports.manage')): ?><a class="btn btn-ghost" href="<?= e(url('imports')) ?>">Import from Excel</a><?php endif; ?>
        <?php if ($classId): ?>
            <details class="xg-cols xg-print"><summary class="btn">Print</summary><div>
                <a href="<?= e(url('pupils/class-list', ['class' => $classId])) ?>" target="_blank">Class list</a>
                <a href="<?= e(url('pupils/class-list', ['class' => $classId, 'cols' => 10])) ?>" target="_blank">Blank mark sheet</a>
                <a href="<?= e(url('pupils/id-cards', ['class' => $classId])) ?>" target="_blank">ID cards</a>
            </div></details>
        <?php endif; ?>
        <?= export_grid_menu() ?>
        <?php if (can('admission.manage')): ?><a class="btn btn-primary" href="<?= e(url('admission/create')) ?>">+ Admit a student</a><?php endif; ?>
    </div>
</div>
    <div class="xg-scroll">
        <table class="xgrid">
            <thead>
                <tr>
                    <th class="xg-n" scope="col">#</th>
                    <?php foreach ($cols as $k => [$t]): ?><th scope="col" data-c="<?= e($k) ?>" data-type="<?= $types[$k] ?? 'text' ?>"><button type="button" class="xg-sort" title="Sort"><?= e($t) ?><span class="xg-arrow" aria-hidden="true"></span></button></th><?php endforeach; ?>
                </tr>

            </thead>
            <tbody>
            <?php foreach ($pupils as $i => $p): $href = e(url('pupils/' . $p['id'])); $dob = (string) $p['date_of_birth']; ?>
                <tr>
                    <td class="xg-n"><?= $i + 1 ?></td>
                    <?php if (isset($show['adm'])): ?><td data-c="adm"><a href="<?= $href ?>"><?= e($p['admission_no']) ?></a></td><?php endif; ?>
                    <?php if (isset($show['surname'])): ?><td data-c="surname"><a href="<?= $href ?>"><?= e($p['last_name']) ?></a></td><?php endif; ?>
                    <?php if (isset($show['first'])): ?><td data-c="first"><?= e(trim($p['first_name'] . ' ' . ($p['middle_name'] ?? ''))) ?></td><?php endif; ?>
                    <?php if (isset($show['class'])): ?><td data-c="class"><?= e($p['class_name'] ?? '') ?></td><?php endif; ?>
                    <?php if (isset($show['gender'])): ?><td data-c="gender"><?= e(ucfirst((string) $p['gender'])) ?></td><?php endif; ?>
                    <?php if (isset($show['age'])): ?><td data-c="age" class="num" data-s="<?= $dob ? (int) ((time() - strtotime($dob)) / 86400) : '' ?>"><?= e(age_from($p['date_of_birth'])) ?></td><?php endif; ?>
                    <?php if (isset($show['dob'])): ?><td data-c="dob" data-s="<?= e($dob) ?>"><?= $dob ? e(fmt_date($dob, 'd/m/Y')) : '' ?></td><?php endif; ?>
                    <?php if (isset($show['parent'])): ?><td data-c="parent"><?= e($p['guardian_name'] ?? '') ?></td><?php endif; ?>
                    <?php if (isset($show['phone'])): ?><td data-c="phone"><?= e($p['guardian_phone'] ?? '') ?></td><?php endif; ?>
                    <?php if (isset($show['house'])): ?><td data-c="house"><?= e($p['house'] ?? '') ?></td><?php endif; ?>
                    <?php if (isset($show['church'])): ?><td data-c="church"><?= e($p['denomination'] ?? '') ?></td><?php endif; ?>
                    <?php if (isset($show['admitted'])): ?><td data-c="admitted" data-s="<?= e((string) $p['admitted_on']) ?>"><?= $p['admitted_on'] ? e(fmt_date($p['admitted_on'], 'd/m/Y')) : '' ?></td><?php endif; ?>
                    <?php if (isset($show['status'])): ?><td data-c="status"><span class="xg-st st-<?= e($p['status']) ?>"><?= e($statusLabels[$p['status']] ?? ucfirst((string) $p['status'])) ?></span></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="xg-empty muted"<?= $pupils ? ' hidden' : '' ?> data-xg-empty>No students found.</p>
    </div>
</div>
