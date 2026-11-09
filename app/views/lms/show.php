<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('lms')) ?>">Learning materials</a> / <?= e($m['class_name']) ?><?= $m['subject_name'] ? ' · ' . e($m['subject_name']) : '' ?></div>
    <h1><?= e($m['title']) ?> <?= $m['status'] === 'draft' ? badge('Draft', 'neutral') : '' ?></h1>
    <p class="muted"><?= e(Learning::KINDS[$m['kind']]) ?> · <?= e($m['teacher_name'] ?? '') ?> · <?= e(fmt_date($m['created_at'], 'D j M Y')) ?><?= $m['strand_name'] ? ' · Strand: ' . e($m['strand_name']) : '' ?></p></div>
    <?php if ($manage): ?><div class="actions"><a class="btn" href="<?= e(url('lms/' . $m['id'] . '/edit')) ?>">Edit</a>
        <form method="post" action="<?= e(url('lms/' . $m['id'] . '/delete')) ?>" data-confirm="Delete this material?" class="inline"><?= csrf_field() ?><button class="btn btn-ghost" type="submit">Delete</button></form></div><?php endif; ?>
</div>
<div class="cols">
<div>
    <div class="card">
        <?php if ($yt): ?>
        <div style="position:relative;padding-top:56.25%;border-radius:12px;overflow:hidden;background:#000;margin-bottom:1rem"><iframe src="https://www.youtube-nocookie.com/embed/<?= e($yt) ?>?rel=0" title="<?= e($m['title']) ?>" style="position:absolute;inset:0;width:100%;height:100%;border:0" allow="accelerometer; encrypted-media; picture-in-picture" allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe></div>
        <?php elseif ($m['url']): ?>
        <p><a class="btn btn-primary" href="<?= e($m['url']) ?>" target="_blank" rel="noopener noreferrer"><?= $m['kind'] === 'video' ? 'Watch the video' : 'Open the link' ?> ↗</a> <small class="muted"><?= e(parse_url($m['url'], PHP_URL_HOST) ?: '') ?></small></p>
        <?php endif; ?>
        <?php if ($m['body']): ?><div class="lms-note" style="line-height:1.7;font-size:1.02rem"><?= nl2br(e($m['body'])) ?></div><?php endif; ?>
        <?php if ($m['attachment']): ?><p style="margin-top:1rem"><a class="btn" href="<?= e(url('lms/' . $m['id'] . '/file')) ?>" target="_blank">📎 <?= e($m['attachment_name']) ?></a></p><?php endif; ?>
    </div>
</div>
<div>
    <?php if ($manage): $n = count(array_filter($readers, fn($r) => $r['seen_at'])); ?>
    <div class="card"><h2>Who has opened it</h2>
        <p><strong><?= $n ?></strong> of <?= count($readers) ?> students<?= $parentViews ? ' · ' . $parentViews . ' parent(s)' : '' ?></p>
        <div class="table-wrap" style="max-height:420px;overflow:auto"><table>
            <?php foreach ($readers as $r): ?><tr><td><?= e($r['last_name'] . ', ' . $r['first_name']) ?></td><td><?= $r['seen_at'] ? badge(fmt_date($r['seen_at'], 'j M H:i'), 'good') : '<small class="muted">' . ($r['user_id'] ? 'Not yet' : 'No student login') . '</small>' ?></td></tr><?php endforeach; ?>
        </table></div>
    </div>
    <?php endif; ?>
</div>
</div>
