<?php $wt = 'welfare/cases'; [$kl, $kt] = Welfare::KINDS[$c['kind']]; [$sl, $st] = Welfare::STATUS[$c['status']]; [$rl, $rt] = Welfare::RISK[$c['risk']]; ?>
<div class="page-head"><div><h1><?= e($c['ref_no']) ?> · <?= e($pupil['first_name'] . ' ' . $pupil['last_name']) ?></h1>
    <p class="muted"><?= badge($kl, $kt) ?> <?= badge($sl, $st) ?> <?= badge('Risk: ' . $rl, $rt) ?> · <?= e($pupil['class_name'] ?? '') ?> · opened <?= e(fmt_date($c['created_at'], 'j M Y')) ?> by <?= e($opener) ?></p></div>
    <div class="actions"><a class="btn" href="<?= e(url('welfare/cases')) ?>">← Cases</a><?php if (can('pupils.view')): ?><a class="btn" href="<?= e(url('pupils/' . $pupil['id'])) ?>">Student record</a><?php endif; ?></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<?php if (!$details): ?>
<div class="card"><p>Thank you for raising this concern. It is with the safeguarding lead, who will act on it in confidence. Status: <?= badge($sl, $st) ?></p></div>
<?php else: ?>
<div class="grid-2">
    <div class="card"><h2><?= e($c['title']) ?></h2>
        <p><?= nl2br(e($c['details'] ?? '')) ?: '<span class="muted">No details written.</span>' ?></p>
        <dl class="details">
            <dt>Followed up by</dt><dd><?= e($assigned ?? ($c['kind'] === 'safeguarding' ? 'Safeguarding lead' : '—')) ?></dd>
            <dt>Referred to</dt><dd><?= e($c['referred_to'] ?? '—') ?></dd>
            <?php if ($c['kind'] === 'incident'): ?><dt>First aid</dt><dd><?= e($c['first_aid'] ?? '—') ?></dd><dt>Parent told</dt><dd><?= $c['parent_informed'] ? 'Yes, by SMS' : 'No' ?></dd><?php endif; ?>
            <dt>Follow up on</dt><dd><?= $c['follow_up_on'] ? e(fmt_date($c['follow_up_on'], 'D j M Y')) : '—' ?></dd>
            <?php if ($c['outcome']): ?><dt>Outcome</dt><dd><?= e($c['outcome']) ?></dd><?php endif; ?>
        </dl>
        <h3>Notes</h3>
        <?php foreach ($notes as $n): ?><div class="w-note-item"><small class="muted"><?= e(fmt_date($n['created_at'], 'D j M Y H:i')) ?> · <?= e($n['by_name'] ?? '') ?></small><p style="margin:.2rem 0 .7rem"><?= nl2br(e($n['note'])) ?></p></div><?php endforeach; ?>
        <?php if (!$notes): ?><p class="muted">No notes yet.</p><?php endif; ?>
        <form method="post" action="<?= e(url('welfare/cases/' . $c['id'] . '/note')) ?>" class="form"><?= csrf_field() ?>
            <label>Add a note (call made, session held, what was agreed) <textarea name="note" rows="3" required></textarea></label>
            <button class="btn" type="submit">Add note</button>
        </form>
    </div>
    <?php if ($work): ?>
    <div class="card"><h2>Follow-up</h2>
        <form method="post" action="<?= e(url('welfare/cases/' . $c['id'])) ?>" class="form"><?= csrf_field() ?>
            <div class="grid-2">
                <label class="required">Status <select name="status"><?php foreach (Welfare::STATUS as $k => [$l]): ?><option value="<?= e($k) ?>"<?= selected($c['status'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
                <label class="required">Risk <select name="risk"><?php foreach (Welfare::RISK as $k => [$l]): ?><option value="<?= e($k) ?>"<?= selected($c['risk'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
            </div>
            <div class="grid-2">
                <label>Referred to <select name="referred_to"><option value="">—</option><?php foreach (Welfare::REFERRALS as $r): ?><option<?= selected($c['referred_to'], $r) ?>><?= e($r) ?></option><?php endforeach; ?></select></label>
                <label>Next follow-up <input type="date" name="follow_up_on" value="<?= e((string) $c['follow_up_on']) ?>"></label>
            </div>
            <?php if (can('welfare.manage') && $c['kind'] !== 'safeguarding'): ?><label>Followed up by <select name="assigned_to"><option value="">—</option><?php foreach ($staff as $u): ?><option value="<?= (int) $u['id'] ?>"<?= selected((int) $c['assigned_to'], (int) $u['id']) ?>><?= e($u['name']) ?></option><?php endforeach; ?></select></label><?php endif; ?>
            <label>Outcome (needed to close) <textarea name="outcome" rows="2" maxlength="500"><?= e($c['outcome'] ?? '') ?></textarea></label>
            <button class="btn btn-primary" type="submit">Save</button>
        </form>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
