<?php $ct = 'marketing'; ?>
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('marketing')) ?>">Campaigns</a> /</div><h1><?= e($c['name']) ?></h1>
    <p><?= $c['status'] === 'sent' ? badge('Sent ' . fmt_date($c['sent_at'], 'D j M Y, H:i'), 'good') . ' <span class="muted">by ' . e($c['sent_name'] ?? '') . '</span>' : badge('Draft — not sent yet', 'warn') ?></p></div>
</div>
<div class="cols">
<div>
    <div class="card"><h2>Message</h2><p style="background:#f3f7fd;border-radius:12px;padding:.8rem 1rem;white-space:pre-wrap"><?= e($body) ?></p>
        <p class="muted"><?= mb_strlen($body) ?> characters · about <?= Notifier::segments($body) ?> SMS each · to: <?= e(Crm::AUDIENCES[$c['audience']] ?? '') ?><?= $c['audience_value'] ? ' (' . e(Crm::STAGES[$c['audience_value']][0] ?? strtoupper($c['audience_value'])) . ')' : '' ?></p></div>
    <?php if ($c['status'] === 'sent'): ?>
    <div class="card" style="padding:0"><h2 style="padding:1rem 1rem 0">Recipients</h2><div class="table-wrap"><table>
        <tr><th>Phone</th><th>Name</th><th>Status</th></tr>
        <?php foreach ($list as $m): ?><tr><td><?= e($m['phone']) ?></td><td><?= e($m['name'] ?? '') ?></td><td><?= $m['ostatus'] ? badge(ucfirst($m['ostatus']) . ($m['channel'] === 'whatsapp' ? ' (WhatsApp)' : ''), $m['ostatus'] === 'sent' ? 'good' : ($m['ostatus'] === 'failed' ? 'bad' : 'neutral')) : '<span class="muted">not sent</span>' ?><?= $m['read_at'] ? ' ' . badge('Read', 'info') : '' ?></td></tr><?php endforeach; ?>
    </table></div></div>
    <?php endif; ?>
</div>
<div>
    <?php if ($c['status'] === 'draft'): ?>
    <div class="card"><h2>Ready to send?</h2>
        <p style="font-size:1.4rem;margin:.2rem 0"><strong><?= count($people) ?></strong> people</p>
        <?php if (count($people) > Crm::MAX_RECIPIENTS): ?><p class="alert alert-warning">That is more than <?= Crm::MAX_RECIPIENTS ?>. Choose a smaller group.</p><?php endif; ?>
        <?php if ($people): ?><p class="muted"><?= e(implode(', ', array_slice(array_map(fn($p, $v) => ($v[0] ? $v[0] . ' ' : '') . $p, array_keys($people), $people), 0, 6))) ?><?= count($people) > 6 ? '…' : '' ?></p>
        <form method="post" action="<?= e(url('marketing/' . $c['id'] . '/send')) ?>" class="form"><?= csrf_field() ?>
            <label style="font-weight:400"><input type="checkbox" name="confirm" value="1" required> Send this message to <?= count($people) ?> people now</label>
            <button class="btn btn-primary" type="submit">Send campaign</button>
        </form><?php else: ?><p class="muted">Nobody matches this group.</p><?php endif; ?>
        <form method="post" action="<?= e(url('marketing/' . $c['id'] . '/delete')) ?>" data-confirm="Delete this draft?" style="margin-top:.6rem"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" type="submit">Delete draft</button></form>
    </div>
    <?php else: ?>
    <div class="card"><h2>Results</h2><dl class="details">
        <dt>Sent to</dt><dd><?= (int) $c['recipients'] ?> people</dd>
        <dt>Delivered</dt><dd><?= $res['sent'] ?><?= $res['queued'] ? ' (' . $res['queued'] . ' waiting)' : '' ?><?= $res['failed'] ? ' · ' . $res['failed'] . ' failed' : '' ?></dd>
        <dt>Read on WhatsApp</dt><dd><?= $res['seen'] ?></dd>
        <dt>Enquiries moved on</dt><dd><?= $res['moved'] ?> <small class="muted">(booked a visit, visited, applied or enrolled since)</small></dd>
        <dt>Applications since</dt><dd><?= $res['applications'] ?> <small class="muted">from these phone numbers</small></dd>
    </dl></div>
    <?php endif; ?>
</div>
</div>
