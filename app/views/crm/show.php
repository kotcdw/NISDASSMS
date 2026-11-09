<?php [$sl, $st] = Crm::STAGES[$e['stage']]; ?>
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('crm')) ?>">CRM</a> / <?= e($e['ref_no']) ?></div><h1><?= e($e['parent_name']) ?></h1>
    <p><?= badge($sl, $st) ?> <span class="muted"><?= e($e['phone']) ?><?= $e['child_name'] ? ' · for ' . e($e['child_name']) : '' ?><?= $e['level_name'] ? ' (' . e($e['level_name']) . ')' : '' ?></span></p></div>
    <div class="actions">
        <a class="btn" href="tel:<?= e($e['phone']) ?>">Call</a>
        <form method="post" action="<?= e(url('crm/' . $e['id'] . '/link')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn-primary" type="submit"<?= $e['opted_out'] ? ' disabled' : '' ?>>SMS the application link</button></form>
    </div>
</div>
<div class="cols">
<div>
    <form method="post" action="<?= e(url('crm/' . $e['id'] . '/activity')) ?>" class="card form"><?= csrf_field() ?><h2>Log a follow-up</h2>
        <div class="grid-2">
            <label>What happened<select name="kind"><?php foreach (Crm::ACTIVITIES as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></label>
            <label>Stage now<select name="stage" data-crm-stage><?php foreach (Crm::STAGES as $k => [$l]): ?><option value="<?= e($k) ?>"<?= selected($e['stage'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
        </div>
        <label>Notes<textarea name="note" rows="2" maxlength="1000" placeholder="e.g. Called; mother will visit on Saturday with the child"></textarea></label>
        <div class="grid-2">
            <label>Next follow-up<input type="date" name="next_follow_up" min="<?= e(date('Y-m-d')) ?>" value="<?= e($e['next_follow_up'] && $e['next_follow_up'] > date('Y-m-d') ? $e['next_follow_up'] : '') ?>"></label>
            <label>School visit (if booked)<input type="datetime-local" name="visit_at" value="<?= e($e['visit_at'] ? date('Y-m-d\TH:i', strtotime($e['visit_at'])) : '') ?>"></label>
        </div>
        <label>Reason (if not joining)<input name="lost_reason" maxlength="255" value="<?= e($e['lost_reason'] ?? '') ?>" placeholder="e.g. fees too high, moved away, chose another school"></label>
        <button class="btn btn-primary" type="submit">Save</button>
    </form>
    <div class="card"><h2>History</h2>
        <?php foreach ($acts as $a): ?><div style="border-top:1px solid #eef1f5;padding:.45rem 0"><small class="muted"><?= e(fmt_date($a['created_at'], 'D j M Y, H:i')) ?> · <?= e($a['kind'] === 'stage' ? 'Stage' : Crm::ACTIVITIES[$a['kind']]) ?><?= $a['by_name'] ? ' · ' . e($a['by_name']) : '' ?></small><br><?= nl2br(e($a['note'] ?? '')) ?></div><?php endforeach; ?>
    </div>
</div>
<div>
    <div class="card"><dl class="details">
        <dt>Next follow-up</dt><dd><?= $e['next_follow_up'] ? e(fmt_date($e['next_follow_up'], 'D j M Y')) : '—' ?></dd>
        <?php if ($e['visit_at']): ?><dt>School visit</dt><dd><?= e(fmt_date($e['visit_at'], 'D j M, H:i')) ?></dd><?php endif; ?>
        <?php if ($e['application_id']): ?><dt>Application</dt><dd><?php if (can('admission.view')): ?><a href="<?= e(url('admission/' . $e['application_id'])) ?>"><?= e($e['app_ref']) ?></a><?php else: ?><?= e($e['app_ref']) ?><?php endif; ?> (<?= e(Admission::label($e['app_stage'])) ?>)</dd><?php endif; ?>
        <?php if ($e['lost_reason']): ?><dt>Not joining</dt><dd><?= e($e['lost_reason']) ?></dd><?php endif; ?>
        <dt>Messages</dt><dd><?= $e['opted_out'] ? 'Asked not to receive campaigns' : 'Allowed' ?>
            <form method="post" action="<?= e(url('crm/' . $e['id'] . '/optout')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" type="submit"><?= $e['opted_out'] ? 'Allow again' : 'Stop messages' ?></button></form></dd>
    </dl></div>
    <form method="post" action="<?= e(url('crm/' . $e['id'])) ?>" class="card form"><?= csrf_field() ?><h2>Details</h2>
        <?php require __DIR__ . '/_fields.php'; ?>
        <button class="btn" type="submit">Save details</button>
    </form>
</div>
</div>
