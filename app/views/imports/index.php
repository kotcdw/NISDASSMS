<div class="page-head"><div><h1>Import from Excel or Word</h1></div></div>
<?php if (!empty($_GET['slips'])): ?><div class="alert alert-success">Staff sign-in slips are ready. <a class="btn btn-sm btn-primary" href="<?= e(url('logins/slips')) ?>" target="_blank" rel="noopener">Print slips</a></div><?php endif; ?>
<div class="cols">
    <div>
        <?php foreach ($kinds as $key => $k): ?>
        <div class="card">
            <h2><?= e($k['label']) ?></h2>
            <form method="post" action="<?= e(url('imports')) ?>" enctype="multipart/form-data" class="filters" style="margin:.4rem 0 .6rem">
                <?= csrf_field() ?><input type="hidden" name="kind" value="<?= e($key) ?>">
                <input type="file" name="file" accept=".xlsx,.docx,.csv" required aria-label="File for <?= e($k['label']) ?>">
                <button class="btn btn-primary" type="submit">Upload &amp; check</button>
                <?= export_menu(url('imports/template', ['kind' => $key]), 'Download template', 'btn btn-ghost') ?>
            </form>
            <details><summary class="hint" style="cursor:pointer">Columns</summary>
                <table style="margin-top:.4rem"><?php foreach ($k['columns'] as $col => $note): ?><tr><td><code><?= e($col) ?></code></td><td><small class="muted"><?= e($note) ?></small></td></tr><?php endforeach; ?></table>
            </details>
                                                        </div>
        <?php endforeach; ?>
    </div>
    <div class="card">
        <h2>How to prepare the file</h2>
        <ol style="padding-left:1.1rem;font-size:.92rem;line-height:1.6">
            <li>Download the template in Excel or Word and open it.</li>
            <li>Type your data on the <strong>Fill in</strong> sheet from row 2, one per row. Keep row 1 (headings) as it is. Orange headings are required; the <strong>How to fill</strong> sheet explains every column.</li>
            <li>Dates can be written 21/03/2016 (day first). Phone numbers like 0244123456.</li>
            <li>Order: staff accounts and staff records first, then students and parents, then opening fee balances (they use the students' admission numbers).</li>
            <li>In Word, type into the table (one row per person; add rows with Tab in the last cell). Keep the heading row as it is.</li>
            <li>Save as Excel workbook (.xlsx), Word document (.docx) or CSV, then upload it here.</li>
        </ol>
        <h3>Previous imports</h3>
        <?php foreach ($history as $h): ?><p style="margin:0 0 .3rem;font-size:.85rem"><?= e(date('d M Y H:i', strtotime($h['created_at'] . ' UTC'))) ?> · <?= e(Importer::KINDS[$h['kind']]['label'] ?? $h['kind']) ?> · <?= (int) $h['rows_imported'] ?> imported<?= $h['rows_skipped'] ? ', ' . (int) $h['rows_skipped'] . ' skipped' : '' ?> <small class="muted">(<?= e($h['by_name'] ?? '') ?>)</small></p><?php endforeach; ?>
        <?php if (!$history): ?><p class="muted">None yet.</p><?php endif; ?>
    </div>
</div>
