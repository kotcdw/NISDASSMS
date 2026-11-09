<?php $docTitle = "HEAD TEACHER'S LOG BOOK"; $docSub = fmt_date($from) . ' – ' . fmt_date($to); ?>
<div class="card-page doc-page">
    <?php require dirname(__DIR__) . '/fees/_doc_head.php'; ?>
    <table class="lines" style="width:100%">
        <tr><th style="text-align:left;width:16%">Date</th><th style="text-align:left;width:16%">Kind</th><th style="text-align:left">Entry</th><th style="text-align:left;width:16%">By</th></tr>
        <?php foreach ($rows as $r): ?><tr><td><?= e(fmt_date($r['entry_date'])) ?></td><td><?= e(Registers::LOG_CATEGORIES[$r['category']] ?? $r['category']) ?></td><td><strong><?= e($r['title']) ?></strong><br><?= nl2br(e($r['body'])) ?></td><td><?= e($r['author'] ?? '') ?></td></tr><?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="4">No entries.</td></tr><?php endif; ?>
    </table>
</div>
