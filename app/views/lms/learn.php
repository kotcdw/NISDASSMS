<?php $bySubject = []; foreach ($rows as $m) { $bySubject[$m['subject_name'] ?? 'General'][] = $m; } ksort($bySubject); ?>
<div class="page-head">
    <div><h1>Learning materials</h1></div>
    <div class="actions"><a class="btn" href="<?= e(url('live')) ?>">Live classes</a></div>
</div>
<?php foreach ($bySubject as $subj => $list): ?>
<div class="card" style="padding:0"><h2 style="padding:1rem 1rem 0"><?= e($subj) ?></h2><div class="table-wrap"><table>
    <?php foreach ($list as $m): ?><tr>
        <td><a href="<?= e(url('lms/' . $m['id'])) ?>"><strong><?= e($m['title']) ?></strong></a> <?= isset($seen[(int) $m['id']]) ? '' : badge('New', 'info') ?><br><small class="muted"><?= e(Learning::KINDS[$m['kind']]) ?> · <?= e($m['teacher_name'] ?? '') ?><?= Auth::role() === 'parent' ? ' · ' . e($m['class_name']) : '' ?></small></td>
        <td style="white-space:nowrap"><?= e(fmt_date($m['created_at'], 'j M')) ?></td>
    </tr><?php endforeach; ?>
</table></div></div>
<?php endforeach; ?>
<?php if (!$rows): ?><div class="card"><p class="muted">Nothing posted yet.</p></div><?php endif; ?>
