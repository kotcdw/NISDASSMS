<?php $today = date('Y-m-d'); $openForm = old('requester') !== ''; ?>
<?php require dirname(__DIR__) . '/setup/_tabs.php'; ?>
<div class="xgrid-box" data-xgrid="data-requests" data-xgrid-file="data-requests">
<div class="page-head">
    <div><h1>Data requests</h1></div>
    <div class="actions">
        <button type="button" class="btn btn-ghost" data-help="reply-days" aria-expanded="false">Reply time</button>
        <?= export_grid_menu() ?>
        <button type="button" class="btn btn-primary" data-help="new-request" aria-expanded="<?= $openForm ? 'true' : 'false' ?>">+ Log a request</button>
    </div>
</div>
<form id="new-request" method="post" action="<?= e(url('setup/privacy/requests')) ?>" class="card form list-form"<?= $openForm ? '' : ' hidden' ?>>
    <?= csrf_field() ?>
    <h2>Log a request</h2>
    <div class="grid-2">
        <label class="required">Who asked<input name="requester" maxlength="150" required value="<?= e(old('requester')) ?>"></label>
        <label>They are<select name="relationship"><?php foreach (Registers::DR_RELATIONS as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old('relationship', 'parent'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
        <label>Phone or email<input name="contact" maxlength="150" value="<?= e(old('contact')) ?>"></label>
        <label class="required">They want to<select name="request_type"><?php foreach (Registers::DR_TYPES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old('request_type', 'access'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
        <label class="required">Received on<input type="date" name="received_on" max="<?= e($today) ?>" value="<?= e(old('received_on', $today)) ?>" required></label>
        <label>Whose records<input name="about" maxlength="150" value="<?= e(old('about')) ?>" placeholder="e.g. Ama Mensah, Basic 4"></label>
    </div>
    <label>Details<textarea name="details" rows="3" maxlength="1000"><?= e(old('details')) ?></textarea></label>
    <div class="gate-actions"><button class="btn btn-primary" type="submit">Log request</button><button type="button" class="btn btn-ghost" data-help="new-request">Cancel</button></div>
</form>
<form id="reply-days" method="post" action="<?= e(url('setup/privacy/request-days')) ?>" class="card form list-form" hidden>
    <?= csrf_field() ?>
    <label>Reply within (days)<input type="number" name="data_request_days" min="1" max="90" value="<?= (int) $days ?>" style="max-width:10rem"></label>
    <div class="gate-actions"><button class="btn btn-primary" type="submit">Save</button><button type="button" class="btn btn-ghost" data-help="reply-days">Cancel</button></div>
</form>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['ref' => ['Ref'], 'received' => ['Received', 'date'], 'who' => ['Who'], 'rel' => ['They are'], 'contact' => ['Contact'], 'type' => ['Request'], 'about' => ['About'], 'details' => ['Details'], 'due' => ['Reply by', 'date'], 'status' => ['Status'], 'closed' => ['Closed on', 'date'], 'handler' => ['Handled by'], 'response' => ['What was done']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $open = $r['status'] === 'open'; ?>
    <tr>
        <td data-c="ref"><strong><?= e($r['ref_no']) ?></strong></td>
        <td data-c="received" data-s="<?= e($r['received_on']) ?>"><?= e(fmt_date($r['received_on'])) ?></td>
        <td data-c="who"><?= e($r['requester']) ?></td>
        <td data-c="rel"><?= e(Registers::DR_RELATIONS[$r['relationship']] ?? '') ?></td>
        <td data-c="contact"><?= e($r['contact'] ?? '') ?></td>
        <td data-c="type"><?= e(Registers::DR_TYPES[$r['request_type']] ?? '') ?></td>
        <td data-c="about"><?= e($r['about'] ?? '') ?></td>
        <td data-c="details" class="audit-details"><?= e($r['details'] ?? '') ?></td>
        <td data-c="due" data-s="<?= e($r['due_on']) ?>"><?= e(fmt_date($r['due_on'])) ?></td>
        <td data-c="status"><?= $open ? ($r['due_on'] < $today ? badge('Overdue', 'bad') : badge('Open', 'warn')) : badge($r['status'] === 'closed' ? 'Closed' : 'Refused', $r['status'] === 'closed' ? 'good' : 'neutral') ?></td>
        <td data-c="closed" data-s="<?= e((string) $r['closed_on']) ?>"><?= !$open && $r['closed_on'] ? e(fmt_date($r['closed_on'])) : '' ?></td>
        <td data-c="handler"><?= $open ? '' : e($r['handler'] ?? '') ?></td>
        <td data-c="response" class="audit-details"><?= e($r['response'] ?? '') ?></td>
        <td class="xg-act" style="text-align:left"><?php if ($open): ?>
            <form method="post" action="<?= e(url('setup/privacy/requests')) ?>" class="form" style="min-width:200px"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <input name="response" maxlength="1000" placeholder="What was done" aria-label="What was done">
                <div style="display:flex;gap:.4rem;margin-top:.3rem"><button class="btn btn-sm btn-primary" name="status" value="closed">Close</button><button class="btn btn-sm" name="status" value="refused">Refuse</button></div></form>
        <?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No requests logged.</p>
</div>
</div>
