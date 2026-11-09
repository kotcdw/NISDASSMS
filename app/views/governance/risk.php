<?php $gt = 'governance/risks'; $mg = can('governance.manage'); $v = fn($k, $d = '') => old($k, $r[$k] ?? $d); $scale = [1 => '1 — Very low', 2 => '2 — Low', 3 => '3 — Medium', 4 => '4 — High', 5 => '5 — Very high']; ?>
<div class="page-center">
<div class="page-head"><div><h1><?= $r ? e($r['ref_no'] . ' · ' . $r['title']) : 'Record a risk' ?></h1>
    <?php if ($r): $sc = $r['likelihood'] * $r['impact']; [$lv, $lt] = Governance::level($sc); ?><p class="muted">Score <?= badge($sc . ' ' . $lv, $lt) ?> · <?= $r['status'] === 'open' ? 'Open' : 'Closed' ?></p><?php endif; ?></div>
    <div class="actions"><a class="btn" href="<?= e(url('governance/risks')) ?>">← Risk register</a></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="card">
<form method="post" action="<?= e(url($r ? 'governance/risks/' . $r['id'] : 'governance/risks')) ?>" class="form"><?= csrf_field() ?>
    <fieldset<?= $mg ? '' : ' disabled' ?> style="border:0;padding:0;margin:0">
    <label class="required">Risk <input name="title" required value="<?= e($v('title')) ?>" placeholder="e.g. Fee arrears above 30% threaten salaries"></label>
    <div class="grid-2">
        <label class="required">Type <select name="category"><?php foreach (Governance::RISK_CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($v('category', 'operational'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
        <label>Owner <input name="owner" value="<?= e($v('owner')) ?>" placeholder="e.g. Bursar"></label>
    </div>
    <div class="grid-2">
        <label class="required">Likelihood <select name="likelihood"><?php foreach ($scale as $k => $l): ?><option value="<?= $k ?>"<?= selected((int) $v('likelihood', 3), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
        <label class="required">Impact <select name="impact"><?php foreach ($scale as $k => $l): ?><option value="<?= $k ?>"<?= selected((int) $v('impact', 3), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
    </div>
    <label>Mitigation (what we do about it) <textarea name="mitigation" rows="3"><?= e($v('mitigation')) ?></textarea></label>
    <div class="grid-3">
        <label class="required">Mitigation status <select name="mitigation_status"><?php foreach (Governance::MITIGATION as $k => [$l]): ?><option value="<?= e($k) ?>"<?= selected($v('mitigation_status', 'not_started'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
        <label>Next review date <input type="date" name="review_date" value="<?= e($v('review_date')) ?>"></label>
        <label>Status <select name="status"><option value="open"<?= selected($v('status', 'open'), 'open') ?>>Open</option><option value="closed"<?= selected($v('status'), 'closed') ?>>Closed</option></select></label>
    </div>
    <?php if ($mg): ?><button class="btn btn-primary" type="submit">Save risk</button><?php endif; ?>
    </fieldset>
</form>
</div>
</div>
