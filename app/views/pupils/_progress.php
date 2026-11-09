<?php
/** Progress across terms. Expects $progress from Progress::pupil(). */
$pt = $progress['terms'] ?? [];
$pass = (float) ($passMark ?? 40);
?>
<div class="card">
    <h2>Progress across terms</h2>
    <?php if (count($pt) < 1): ?>
        <p class="muted">No term results yet. The chart appears once scores have been entered<?= !can('staff') ? ' and the report card has been released' : '' ?>.</p>
    <?php elseif (count($pt) === 1): $t0 = $pt[array_key_first($pt)]; // one term: a plain list of scores reads better than one-dot charts ?>
        <p class="muted" style="font-size:.88rem;margin-top:-.4rem"><?= e($t0['name'] . ' · ' . $t0['class']) ?> — charts appear here once there are two or more terms.</p>
        <div class="table-wrap"><table class="score-table">
            <thead><tr><th>Subject</th><th class="num">Score</th><th class="score-bar-col"><span class="sr-only">Compared with the pass mark</span></th></tr></thead>
            <tbody>
            <?php foreach ($progress['subjects'] as $name => $row): $v = $row[array_key_first($row)] ?? null; ?>
                <tr><td><?= e($name) ?></td><td class="num"><?= $v === null ? '<span class="muted">—</span>' : Results::fmt($v) ?></td>
                    <td class="score-bar-col"><?php if ($v !== null): ?><span class="perf-bar <?= $v < $pass ? 'low' : 'ok' ?>"><span style="width:<?= max(0, min(100, (float) $v)) ?>%"></span></span><?php endif; ?></td></tr>
            <?php endforeach; ?>
            <tr class="score-total"><td>Overall average</td><td class="num"><?= Results::fmt($t0['average']) ?></td><td class="score-bar-col"><span class="perf-bar <?= (float) $t0['average'] < $pass ? 'low' : 'ok' ?>"><span style="width:<?= max(0, min(100, (float) $t0['average'])) ?>%"></span></span></td></tr>
            </tbody>
        </table></div>
        <p class="chart-legend"><span>Pass mark: <?= Results::fmt($pass) ?></span></p>
    <?php else: ?>
        <h3 style="margin:.2rem 0 .3rem;font-size:1rem">Overall average</h3>
        <?= Charts::line(array_map(fn($t) => [$t['label'], $t['average'], $t['name'] . ' (' . $t['class'] . '): ' . Results::fmt($t['average']) . '%'], $pt), ['height' => 170, 'max' => 100, 'min' => 0, 'target' => $pass, 'label' => 'Overall average by term', 'format' => fn($v) => Results::fmt((float) $v)]) ?>
        <p class="chart-legend"><span>Dashed line: pass mark (<?= Results::fmt($pass) ?>)</span><span>Hover a dot for the exact score.</span></p>

        <div class="progress-grid">
            <?php foreach ($progress['subjects'] as $name => $row): [$arrow, $cls, $txt] = Progress::trend($row); ?>
            <div class="progress-cell">
                <div class="progress-head"><strong><?= e($name) ?></strong> <span class="<?= e($cls) ?>" title="Change since the previous term"><?= e($arrow . ' ' . $txt) ?></span></div>
                <?= Charts::line(array_map(fn($i) => [$pt[$i]['label'], $row[$i], $name . ', ' . $pt[$i]['name'] . ': ' . ($row[$i] === null ? 'no score' : Results::fmt($row[$i]))], array_keys($pt)), ['width' => 320, 'height' => 120, 'max' => 100, 'min' => 0, 'target' => $pass, 'label' => $name . ' by term', 'format' => fn($v) => Results::fmt((float) $v), 'labelEvery' => max(1, (int) ceil(count($pt) / 4))]) ?>
            </div>
            <?php endforeach; ?>
        </div>

        <details class="sum" style="margin-top:.8rem"><summary>Show the numbers</summary>
        <div class="table-wrap"><table>
            <thead><tr><th>Subject</th><?php foreach ($pt as $t): ?><th class="num" title="<?= e($t['name'] . ' · ' . $t['class']) ?>"><?= e($t['label']) ?></th><?php endforeach; ?><th>Change</th></tr></thead>
            <tbody>
            <?php foreach ($progress['subjects'] as $name => $row): [$arrow, $cls, $txt] = Progress::trend($row); ?>
                <tr><td><?= e($name) ?></td><?php foreach ($row as $v): ?><td class="num"><?= Results::fmt($v) ?></td><?php endforeach; ?><td class="<?= e($cls) ?>"><?= e($arrow . ' ' . $txt) ?></td></tr>
            <?php endforeach; ?>
            <tr><td><strong>Average</strong></td><?php foreach ($pt as $t): ?><td class="num"><strong><?= Results::fmt($t['average']) ?></strong></td><?php endforeach; ?><td></td></tr>
            </tbody>
        </table></div>
        </details>
    <?php endif; ?>
</div>
