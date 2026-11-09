<?php $groups = []; foreach ($checks as $c) { $groups[$c['group']][] = $c; } ?>
<div class="page-head">
    <div><h1>Go-live checklist</h1></div>
    <div class="actions"><a class="btn" href="<?= e(url('golive', ['print' => 1])) ?>" target="_blank" rel="noopener">Print</a></div>
</div>
<div class="cols">
    <div>
        <?php foreach ($groups as $g => $list): ?>
        <div class="card" style="padding:0">
            <h2 style="padding:1rem 1rem 0"><?= e($g) ?></h2>
            <div class="table-wrap"><table>
                <?php foreach ($list as $c): ?>
                <tr>
                    <td style="width:1%"><?= $c['ok'] === true ? badge('Ready', 'good') : ($c['ok'] === false ? badge('Fix', 'bad') : badge('Check', 'warn')) ?></td>
                    <td><strong><?= e($c['label']) ?></strong><br><small class="muted"><?= e($c['detail']) ?></small></td>
                    <td style="width:1%"><?php if ($c['link'] && $c['ok'] !== true): ?><a class="btn btn-sm" href="<?= e(url($c['link'])) ?>">Open</a><?php endif; ?></td>
                </tr>
                <?php endforeach; ?>
            </table></div>
        </div>
        <?php endforeach; ?>
        <div class="card">
            <h2>Trial run</h2>
            
            <?php foreach (GoLive::TRIAL as $who => $items): ?>
                <h3 style="margin:1rem 0 .4rem"><?= e($who) ?></h3>
                <?php foreach ($items as $k => [$label, $detail]): $key = 'trial:' . $k; $link = null; require __DIR__ . '/_tick.php'; endforeach; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <div>
        <div class="card">
            <h2>Decisions to confirm</h2>
            
            <?php foreach (GoLive::CONFIRMATIONS as $k => [$label, $detail, $link]): $key = 'confirm:' . $k; require __DIR__ . '/_tick.php'; endforeach; ?>
        </div>
    </div>
</div>
