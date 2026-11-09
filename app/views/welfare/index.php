<?php $wt = 'welfare'; ?>
<div class="page-head"><div><h1>Behaviour &amp; welfare</h1></div>
    <div class="actions"><details class="menu-btn"><summary class="btn btn-primary">+ New ▾</summary><div>
        <a href="<?= e(url('welfare/behaviour/new')) ?>">⭐ Merit or concern</a>
        <a href="<?= e(url('welfare/cases/new', ['kind' => 'counselling'])) ?>">Counselling case</a>
        <a href="<?= e(url('welfare/cases/new', ['kind' => 'incident'])) ?>">Accident / incident</a>
        <a href="<?= e(url('welfare/cases/new', ['kind' => 'safeguarding'])) ?>">⚠ Safeguarding concern</a>
    </div></details></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<?php $nCases = count($cases); $nRecent = count($recent); ?>
<div class="card ptabs" data-ptabs style="padding:0">
    <div class="ptab-bar" role="tablist" aria-label="Welfare overview">
        <button type="button" role="tab" id="pt-open" aria-controls="pp-open" aria-selected="true" data-ptab="open">Open cases <span class="ptab-n"><?= $nCases ?></span></button>
        <button type="button" role="tab" id="pt-behaviour" aria-controls="pp-behaviour" aria-selected="false" tabindex="-1" data-ptab="behaviour">Latest behaviour records <span class="ptab-n"><?= $nRecent ?></span></button>
    </div>
    <div class="ptab-panel" role="tabpanel" id="pp-open" aria-labelledby="pt-open" data-ppanel="open">
        <div class="table-wrap"><table>
            <thead><tr><th>Ref</th><th>Student</th><th>Case</th><th>Risk</th><th>Followed up by</th><th>Follow-up</th><th>Status</th></tr></thead>
            <?php foreach ($cases as $c): require __DIR__ . '/_case_row.php'; endforeach; ?>
            <?php if (!$cases): ?><tr><td colspan="7" class="muted">✓ No open cases.</td></tr><?php endif; ?>
        </table></div>
        <p class="ptab-foot"><a href="<?= e(url('welfare/cases')) ?>">All welfare cases →</a></p>
    </div>
    <div class="ptab-panel" role="tabpanel" id="pp-behaviour" aria-labelledby="pt-behaviour" data-ppanel="behaviour" hidden>
        <div class="table-wrap"><table>
            <thead><tr><th>Date</th><th>Student</th><th>Record</th><th>Action taken</th><th>By</th></tr></thead>
            <?php foreach ($recent as $b): ?><tr><td><?= e(fmt_date($b['occurred_on'], 'D j M')) ?></td><td><a href="<?= e(url('pupils/' . $b['pupil_id'])) ?>"><?= e($b['first_name'] . ' ' . $b['last_name']) ?></a></td>
                <td><?= $b['kind'] === 'merit' ? badge('Merit', 'good') : badge('Concern', 'warn') ?> <?= e($b['category']) ?></td><td><?= e(Welfare::ACTIONS[$b['action_taken']] ?? '—') ?></td><td><?= e($b['by_name'] ?? '') ?></td></tr><?php endforeach; ?>
            <?php if (!$recent): ?><tr><td colspan="5" class="muted">Nothing recorded yet.</td></tr><?php endif; ?>
        </table></div>
        <p class="ptab-foot"><a href="<?= e(url('welfare/behaviour')) ?>">All behaviour records →</a></p>
    </div>
</div>
