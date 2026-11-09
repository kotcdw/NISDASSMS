<div class="page-head">
    <div><p class="breadcrumb"><a href="<?= e(url('conversations')) ?>">Messages</a></p>
    <h1><?= e($c['subject']) ?> <?= (int) $c['is_closed'] ? badge('Closed') : '' ?></h1>
    <p class="muted"><?= $c['first_name'] ? 'About ' . e($c['first_name'] . ' ' . $c['last_name']) . ' · ' : '' ?><?= e(implode(', ', array_map(fn($m) => $m['name'] . ' (' . Auth::roleLabel($m['role']) . ')', $members))) ?></p></div>
    <?php if (can('staff') && ($member || can('chat.oversee'))): ?><div class="actions"><form method="post" action="<?= e(url('conversations/' . $c['id'] . '/close')) ?>"><?= csrf_field() ?><button class="btn btn-ghost" type="submit"><?= (int) $c['is_closed'] ? 'Reopen' : 'Close conversation' ?></button></form></div><?php endif; ?>
</div>
<div class="card" style="max-width:820px">
    <div class="chat">
        <?php foreach ($messages as $m): ?>
        <div class="msg<?= (int) $m['user_id'] === Auth::id() ? ' me' : '' ?>"><small><?= e($m['name']) ?> · <?= e(date('D j M, H:i', strtotime($m['created_at']))) ?></small><?= nl2br(e($m['body'])) ?></div>
        <?php endforeach; ?>
    </div>
    <?php if (!(int) $c['is_closed']): ?>
    <form method="post" action="<?= e(url('conversations/' . $c['id'] . '/reply')) ?>" class="form" style="margin-top:1rem">
        <?= csrf_field() ?>
        <label>Reply<textarea name="body" rows="4" required maxlength="5000"></textarea></label>
        <button class="btn btn-primary" type="submit">Send</button>
    </form>
    <?php endif; ?>
</div>
