<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('forms/' . $form['id'] . '/responses')) ?>"><?= e($form['title']) ?> responses</a> /</div>
    <h1>Response #<?= (int) $r['id'] ?></h1>
    <p class="muted"><?= e(fmt_date($r['created_at'], 'l j F Y, H:i')) ?><?= $r['respondent_name'] ? ' · ' . e($r['respondent_name']) : '' ?><?= $r['respondent_phone'] ? ' · ' . e($r['respondent_phone']) : '' ?></p></div>
    <div class="actions"><button class="btn btn-ghost" type="button" data-print>Print</button></div>
</div>
<div class="card" style="max-width:820px">
    <dl class="details">
        <?php foreach ($fields as $f): if ($f['type'] === 'section') continue; $a = $answers[$f['id']] ?? null; if ($f['is_archived'] && !$a) continue; ?>
            <dt><?= e($f['label']) ?><?= $f['is_archived'] ? ' <small>(removed)</small>' : '' ?></dt>
            <dd>
                <?php if (!$a): ?><span class="muted">—</span>
                <?php elseif ($f['type'] === 'file'): ?><a href="<?= e(url('forms/' . $form['id'] . '/responses/' . $r['id'] . '/file/' . $f['id'])) ?>" target="_blank" rel="noopener">📎 <?= e($a['file_name']) ?></a>
                <?php else: ?><span style="white-space:pre-line"><?= e(FormDef::display($a['value'], $f['type'])) ?></span><?php endif; ?>
            </dd>
        <?php endforeach; ?>
    </dl>
</div>
