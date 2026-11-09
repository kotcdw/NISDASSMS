<?php $gt = 'governance/meetings'; $mg = can('governance.manage'); ?>
<div class="page-head"><div><h1><?= e($m['title']) ?></h1><p class="muted"><?= e(Governance::MEETING_KINDS[$m['kind']]) ?> · <?= e(fmt_date($m['meeting_date'], 'l j F Y')) ?><?= $m['start_time'] ? ' · ' . e(substr($m['start_time'], 0, 5)) : '' ?><?= $m['venue'] ? ' · ' . e($m['venue']) : '' ?></p></div>
    <div class="actions"><a class="btn" href="<?= e(url('governance/meetings')) ?>">← All meetings</a></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="grid-2">
    <div class="card"><h2>Attendance</h2>
        <?php if (!$members): ?><p class="muted">Add board members first under <a href="<?= e(url('governance/members')) ?>">Board members</a>.</p><?php else: ?>
        <form method="post" action="<?= e(url('governance/meetings/' . $m['id'] . '/attendance')) ?>"><?= csrf_field() ?>
            <div class="table-wrap"><table>
                <tr><th>Member</th><?php foreach (Governance::ATTEND as [$l]): ?><th><?= e($l) ?></th><?php endforeach; ?></tr>
                <?php foreach ($members as $b): ?><tr><td><?= e($b['name']) ?><br><small class="muted"><?= e($b['position'] ?? '') ?></small></td>
                    <?php foreach (Governance::ATTEND as $k => $_): ?><td><input type="radio" name="status[<?= (int) $b['id'] ?>]" value="<?= e($k) ?>"<?= checked($b['status'] === $k) ?><?= $mg ? '' : ' disabled' ?> aria-label="<?= e($b['name'] . ' ' . $k) ?>"></td><?php endforeach; ?></tr>
                <?php endforeach; ?>
            </table></div>
            <?php if ($mg): ?><button class="btn btn-primary" type="submit" style="margin-top:.6rem">Save attendance</button><?php endif; ?>
        </form>
        <?php endif; ?>
    </div>
    <div class="card"><h2>Agenda &amp; decisions</h2>
        <?php if ($mg): require __DIR__ . '/_meeting_form.php'; ?>
            <form method="post" action="<?= e(url('governance/meetings/' . $m['id'] . '/delete')) ?>" data-confirm="Delete this meeting and its attendance?" style="margin-top:.8rem"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">Delete meeting</button></form>
        <?php else: ?>
            <h3>Agenda</h3><p><?= nl2br(e($m['agenda'] ?? '—')) ?></p><h3>Decisions</h3><p><?= nl2br(e($m['decisions'] ?? '—')) ?></p>
        <?php endif; ?>
    </div>
</div>
