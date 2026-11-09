<?php
/* Gate pick-up. Expects $q, $found, $child, $guardians, $people, $today. */
$name = fn($p) => trim($p['first_name'] . ' ' . $p['last_name']);
$st = Pickups::STATUS;
$oldWho = old('who', $guardians ? 'g' . $guardians[0]['id'] : 'other');
?>
<div class="page-head">
    <div><h1>Gate pick-up</h1></div>
    <div class="actions">
        <?php if (can('pickup.manage')): ?><a class="btn btn-ghost" href="<?= e(url('gate/register')) ?>">Pick-up register</a><?php endif; ?>
        <?php if (can('visitors.record')): ?><a class="btn btn-ghost" href="<?= e(url('visitors')) ?>">Visitors' book</a><?php endif; ?>
    </div>
</div>

<form method="get" action="<?= e(url('gate')) ?>" class="card gate-find" role="search">
    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Child's name or admission number" aria-label="Child's name or admission number"<?= $child ? '' : ' autofocus' ?>>
    <button class="btn btn-primary" type="submit">Find</button>
</form>

<?php if ($q !== '' && !$child): ?>
    <?php if (!$found): ?><p class="alert alert-warning">No child found for “<?= e($q) ?>”. Check the spelling or ask for the admission number.</p>
    <?php else: ?>
    <div class="gate-results">
        <?php foreach ($found as $f): ?>
        <a class="card gate-kid" href="<?= e(url('gate', ['pupil' => $f['id']])) ?>">
            <?php if ($f['photo_path']): ?><img src="<?= e(url('gate/pupil/' . $f['id'] . '/photo')) ?>" alt=""><?php else: ?><span class="gate-init"><?= e(mb_substr($f['first_name'], 0, 1)) ?></span><?php endif; ?>
            <span><strong><?= e($name($f)) ?></strong><small><?= e($f['class_name'] ?? '') ?> · <?= e($f['admission_no']) ?></small></span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>

<?php if ($child): ?>
<div class="gate-grid">
    <section class="card gate-child">
        <div class="gate-who">
            <?php if ($child['photo_path']): ?><img src="<?= e(url('gate/pupil/' . $child['id'] . '/photo')) ?>" alt="Photo of <?= e($child['first_name']) ?>"><?php else: ?><span class="gate-init lg"><?= e(mb_substr($child['first_name'], 0, 1)) ?></span><?php endif; ?>
            <div><h2><?= e($name($child)) ?></h2><p class="muted"><?= e($child['class_name'] ?? '') ?> · <?= e($child['admission_no']) ?></p></div>
        </div>
        <h3>Parents / guardians</h3>
        <ul class="gate-list">
            <?php foreach ($guardians as $g): ?><li><span class="gate-init sm"><?= e(mb_substr($g['name'], 0, 1)) ?></span><span><strong><?= e($g['name']) ?></strong><small><?= e($g['relationship'] ?: 'Parent / guardian') ?><?= $g['is_primary'] ? ' · main contact' : '' ?></small></span></li><?php endforeach; ?>
            <?php if (!$guardians): ?><li class="muted">No parent on record — ask the office.</li><?php endif; ?>
        </ul>
        <h3>Allowed by the parent</h3>
        <ul class="gate-list">
            <?php foreach ($people as $p): ?><li><?php if ($p['photo']): ?><img src="<?= e(url('gate/person/' . $p['id'] . '/photo')) ?>" alt=""><?php else: ?><span class="gate-init sm"><?= e(mb_substr($p['name'], 0, 1)) ?></span><?php endif; ?>
                <span><strong><?= e($p['name']) ?></strong><small><?= e($p['relationship'] ?: 'Caretaker') ?><?= $p['id_type'] ? ' · ' . e(Pickups::ID_TYPES[$p['id_type']] ?? '') . ' ' . e($p['id_number'] ?? '') : '' ?></small></span></li><?php endforeach; ?>
            <?php if (!$people): ?><li class="muted">Nobody added yet.</li><?php endif; ?>
        </ul>
        <?php if (can('pickup.manage')): ?>
        <button type="button" class="btn btn-ghost btn-sm" data-help="office-person">+ Add a person the parent allowed</button>
        <form id="office-person" method="post" action="<?= e(url('gate/people')) ?>" enctype="multipart/form-data" class="form gate-office" hidden>
            <?= csrf_field() ?><input type="hidden" name="pupil_id" value="<?= (int) $child['id'] ?>">
            <div class="grid-2"><label class="required">Name<input name="name" required maxlength="120"></label><label class="required">Phone<input name="phone" required maxlength="20" inputmode="tel"></label></div>
            <div class="grid-2"><label>Relationship<input name="relationship" maxlength="60" placeholder="e.g. Aunt"></label><label>ID type<select name="id_type"><option value="">—</option><?php foreach (Pickups::ID_TYPES as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?></select></label></div>
            <div class="grid-2"><label>ID number<input name="id_number" maxlength="40"></label><label>Photo<input type="file" name="photo" accept="image/jpeg,image/png"></label></div>
            <button class="btn btn-primary btn-sm" type="submit">Add</button>
        </form>
        <?php endif; ?>
    </section>

    <form method="post" action="<?= e(url('gate')) ?>" enctype="multipart/form-data" class="card form gate-form" data-gate-form>
        <?= csrf_field() ?><input type="hidden" name="pupil_id" value="<?= (int) $child['id'] ?>">
        <h2>Who is collecting <?= e($child['first_name']) ?>?</h2>
        <div class="gate-choices">
            <?php foreach ($guardians as $g): ?><label class="gate-choice"><input type="radio" name="who" value="g<?= (int) $g['id'] ?>"<?= checked($oldWho === 'g' . $g['id']) ?> data-gate-kind="guardian"><span><strong><?= e($g['name']) ?></strong><small>Parent — released straight away</small></span></label><?php endforeach; ?>
            <?php foreach ($people as $p): ?><label class="gate-choice"><input type="radio" name="who" value="p<?= (int) $p['id'] ?>"<?= checked($oldWho === 'p' . $p['id']) ?> data-gate-kind="listed"><span><strong><?= e($p['name']) ?></strong><small>Allowed by parent — parent approves by SMS</small></span></label><?php endforeach; ?>
            <label class="gate-choice"><input type="radio" name="who" value="other"<?= checked($oldWho === 'other') ?> data-gate-kind="other"><span><strong>Someone else</strong><small>Parent must approve by SMS or code</small></span></label>
        </div>
        <div data-gate-other>
            <div class="grid-2"><label class="required">Full name<input name="collector_name" maxlength="120" value="<?= e(old('collector_name')) ?>"></label><label>Phone<input name="phone" maxlength="20" inputmode="tel" value="<?= e(old('phone')) ?>"></label></div>
            <label>Relationship to the child<input name="relationship" maxlength="60" placeholder="e.g. Uncle, driver, neighbour" value="<?= e(old('relationship')) ?>"></label>
        </div>
        <div class="grid-2">
            <label class="required">ID card<select name="id_type" required><option value="">Choose…</option><?php foreach (Pickups::ID_TYPES as $k => $l): ?><option value="<?= $k ?>"<?= selected(old('id_type'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
            <label class="required">ID number<input name="id_number" required maxlength="40" value="<?= e(old('id_number')) ?>" placeholder="e.g. GHA-123456789-0"></label>
        </div>
        <label data-gate-photo>Photo of the person<input type="file" name="photo" accept="image/jpeg,image/png" capture="user"></label>
        <label data-gate-code>One-time pick-up code from the parent<input name="code" inputmode="numeric" maxlength="7" placeholder="6 digits" autocomplete="off"></label>
        <div class="sigpad-wrap">
            <span class="sigpad-label">Signature of the person collecting <button type="button" class="btn btn-ghost btn-sm" data-sig-clear>Clear</button></span>
            <canvas class="sigpad" data-sigpad="sig-<?= (int) $child['id'] ?>" width="600" height="180" aria-label="Sign here"></canvas>
            <input type="hidden" name="signature" id="sig-<?= (int) $child['id'] ?>">
        </div>
        <div class="gate-actions"><button class="btn btn-primary" type="submit">Record pick-up</button><a class="btn btn-ghost" href="<?= e(url('gate')) ?>">Cancel</a></div>
    </form>
</div>
<?php endif; ?>

<section class="card gate-today" style="padding:0">
    <div class="gate-today-head"><h2>Today at the gate</h2><span class="muted"><?= count($today) ?> pick-up<?= count($today) === 1 ? '' : 's' ?></span></div>
    <?php if ($today): ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Time</th><th>Child</th><th>Collected by</th><th>Status</th><th></th></tr></thead>
        <?php foreach ($today as $t): [$sl, $tone] = $st[$t['status']] ?? [$t['status'], '']; ?>
        <tr>
            <td><?= e(date('g:ia', strtotime($t['created_at']))) ?></td>
            <td><strong><?= e($t['first_name'] . ' ' . $t['last_name']) ?></strong></td>
            <td><?= e($t['collector_name']) ?><br><small class="muted"><?= e(Pickups::KINDS[$t['kind']] ?? '') ?></small></td>
            <td><?= badge($sl, $tone ?: 'neutral') ?></td>
            <td class="num"><a class="btn btn-sm<?= in_array($t['status'], ['waiting', 'approved'], true) ? ' btn-primary' : '' ?>" href="<?= e(url('gate/' . $t['id'])) ?>">Open</a></td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <?php else: ?><p class="muted" style="padding:0 1.2rem 1.2rem">No pick-ups recorded today.</p><?php endif; ?>
</section>
