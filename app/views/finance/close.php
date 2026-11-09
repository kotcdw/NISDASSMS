<?php
$failed = array_filter($checks, fn($c) => !$c[1]);
$done = array_filter($checks, fn($c) => $c[1]);
$mLabel = fn(string $m) => date('F Y', strtotime($m . '-01'));
?>
<div class="page-head">
    <div><h1>Month-end</h1></div>
    <?php if ($closedUntil): ?><div class="actions"><?= badge('Closed to ' . fmt_date($closedUntil), 'good') ?></div><?php endif; ?>
</div>
<div class="cols me-cols">
    <div>
        <?php if ($next): ?>
        <div class="card">
            <div class="me-head">
                <h2>Close <?= e($mLabel($next)) ?></h2>
                <span class="me-count"><?= count($done) ?> / <?= count($checks) ?> done</span>
            </div>

            <?php if ($failed): ?>
            <ul class="me-list">
                <?php foreach ($failed as [$label, $ok, $detail, $href]): ?>
                <li class="me-todo"><span class="me-ic">!</span><a href="<?= e($href) ?>"><?= e($label) ?></a><small><?= e($detail) ?></small></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <?php if ($done): ?>
            <details class="me-done"<?= $failed ? '' : ' open' ?>>
                <summary><?= count($done) ?> done</summary>
                <ul class="me-list">
                    <?php foreach ($done as [$label]): ?><li><span class="me-ic me-ok">✓</span><?= e($label) ?></li><?php endforeach; ?>
                </ul>
            </details>
            <?php endif; ?>

            <?php if (can('finance.close')): ?>
            <form method="post" action="<?= e(url('finance/close')) ?>" class="form me-form" data-confirm="Close <?= e($mLabel($next)) ?>? Money records up to <?= e(fmt_date(date('Y-m-t', strtotime($next . '-01')))) ?> will be locked."><?= csrf_field() ?>
                <input type="hidden" name="month" value="<?= e($next) ?>">
                <?php if ($failed): ?>
                    <label class="me-check"><input type="checkbox" name="anyway" value="1"> Close with <?= count($failed) ?> open</label>
                    <label>Reason<input name="note" maxlength="255"></label>
                <?php endif; ?>
                <button class="btn btn-primary" type="submit">Close <?= e($mLabel($next)) ?></button>
            </form>
            <?php endif; ?>
        </div>
        <?php else: ?><div class="card"><p style="margin:0">Up to date.</p></div><?php endif; ?>

        <?php if ($closedUntil && can('finance.reopen')): ?>
        <details class="card me-reopen">
            <summary>Reopen <?= e(date('F Y', strtotime($closedUntil))) ?></summary>
            <form method="post" action="<?= e(url('finance/close/reopen')) ?>" class="form" data-confirm="Reopen <?= e(date('F Y', strtotime($closedUntil))) ?>?"><?= csrf_field() ?>
                <label class="required">Reason<input name="note" required maxlength="255"></label>
                <button class="btn btn-danger" type="submit">Reopen month</button>
            </form>
        </details>
        <?php endif; ?>
    </div>
    <div>
        <div class="card"><h2>Months</h2>
            <ul class="me-months">
                <li><span><?= e(date('F Y')) ?></span><?= badge('Current', 'neutral') ?><a href="<?= e(url('finance/board-pack', ['month' => date('Y-m')])) ?>" target="_blank" rel="noopener">Board pack</a></li>
                <?php foreach ($months as $mm): ?>
                <li><span><?= e($mLabel($mm['m'])) ?></span><?= $mm['closed'] ? badge('Closed', 'good') : badge('Open', 'neutral') ?><a href="<?= e(url('finance/board-pack', ['month' => $mm['m']])) ?>" target="_blank" rel="noopener">Board pack</a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php if ($log): ?><details class="card me-hist"><summary>History</summary>
            <?php foreach ($log as $l): ?><p><small class="muted"><?= e(date('j M Y', strtotime($l['created_at']))) ?> · <?= e($l['by_name'] ?? '') ?></small><br><?= $l['action'] === 'close' ? 'Closed' : 'Reopened' ?> <?= e($mLabel($l['month'])) ?><?= $l['note'] ? ' — ' . e($l['note']) : '' ?></p><?php endforeach; ?>
        </details><?php endif; ?>
    </div>
</div>
