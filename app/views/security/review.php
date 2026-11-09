<?php $list = function (array $rows, string $empty, ?callable $extra = null) { ?>
    <?php if (!$rows): ?><p class="muted" style="margin:0"><?= e($empty) ?></p><?php else: ?><ul style="margin:0;padding-left:1.1rem"><?php foreach ($rows as $u): ?><li><a href="<?= e(url('users/' . $u['id'] . '/edit')) ?>"><?= e($u['name']) ?></a> <small class="muted"><?= e(Auth::roleLabel($u['role'])) ?><?= $extra ? ' · ' . e($extra($u)) : '' ?></small></li><?php endforeach; ?></ul><?php endif; ?>
<?php }; ?>
<div class="page-head"><div><h1>Access review</h1></div>
    <div class="actions">
        <?= export_grid_menu('Export', 'security-events') ?>
        <details class="menu-btn"><summary class="btn">More ▾</summary><div>
            <?= export_links(url('security/review', ['export' => 1]), 'All staff accounts and permissions —') ?>
        </div></details>
    </div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="grid-2">
    <div class="card" style="margin:0"><h2>Must set up 2-step sign-in <?= $findings['no2fa_required'] ? badge((string) count($findings['no2fa_required']), 'bad') : '' ?></h2><?php $list($findings['no2fa_required'], 'Everyone who must has set it up.'); ?></div>
    <div class="card" style="margin:0"><h2>Sensitive access without 2-step sign-in <?= $findings['no2fa_high'] ? badge((string) count($findings['no2fa_high']), 'warn') : '' ?></h2><?php $list($findings['no2fa_high'], 'None.'); ?></div>
    <div class="card" style="margin:0"><h2>Not used for 60+ days <?= $findings['dormant'] ? badge((string) count($findings['dormant']), 'warn') : '' ?></h2><?php $list($findings['dormant'], 'None.', fn($u) => $u['days'] . ' days'); ?><p class="hint">Switch off accounts of staff who have left.</p></div>
    <div class="card" style="margin:0"><h2>Never signed in / temporary password</h2><?php $list(array_merge($findings['never'], array_filter($findings['temp'], fn($u) => $u['last_login_at'])), 'None.'); ?></div>
</div>
<?php if ($warnings): ?><div class="card"><h2>Combinations to review</h2><ul style="margin:0;padding-left:1.1rem"><?php foreach ($warnings as [$r, $w]): ?><li><strong><?= e($r) ?></strong>: <?= e($w) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="xgrid-box" data-xgrid="security-events" data-xgrid-file="security-events" style="margin-top:1.2rem">
<div class="page-head"><div><h2>Recent security events</h2></div></div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['when' => ['When', 'date'], 'who' => ['Who'], 'what' => ['What'], 'details' => ['Details']]) ?></tr></thead>
    <tbody>
    <?php foreach ($events as $ev): ?>
    <tr>
        <td data-c="when" data-s="<?= e($ev['created_at']) ?>" style="white-space:nowrap"><?= e(date('d M H:i', strtotime($ev['created_at']))) ?></td>
        <td data-c="who"><?= e($ev['user_name'] ?? '—') ?></td>
        <td data-c="what"><?= e(str_replace('_', ' ', $ev['action'])) ?></td>
        <td data-c="details" class="muted" style="word-break:break-word"><?= e(mb_strimwidth((string) $ev['details'], 0, 220, '…')) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $events ? ' hidden' : '' ?> data-xg-empty>No security events yet.</p>
</div>
</div>
