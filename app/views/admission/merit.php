<?php $manage = can('admission.manage'); $canOffer = fn($r) => in_array('offered', Admission::TRANSITIONS[$r['stage']] ?? [], true); ?>
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('admission')) ?>">Admission</a> /</div><h1>Admission List</h1></div>
    <div class="actions">
        <form method="get" class="xg-pick" data-autosubmit><select name="level" aria-label="Class"><?php foreach ($levels as $l): ?><option value="<?= (int) $l['id'] ?>"<?= selected($l['id'], $lid) ?>><?= e($l['name']) ?><?= (int) $l['n'] ? ' (' . (int) $l['n'] . ')' : '' ?></option><?php endforeach; ?></select></form>
        <?= export_menu(url('admission/merit', ['level' => $lid, 'csv' => 1]), 'Export', 'btn') ?>
    </div>
</div>
<form method="post" class="card" style="padding:0"><?= csrf_field() ?><input type="hidden" name="level" value="<?= (int) $lid ?>">
    <div class="table-wrap"><table>
        <tr><?php if ($manage): ?><th></th><?php endif; ?><th>Rank</th><th>Applicant</th><th>Entrance (0–100)</th><th>Interview (0–100)</th><th class="num">Merit</th><th>Stage</th></tr>
        <?php foreach ($rows as $r): $edit = $manage && in_array($r['stage'], ['submitted', 'screening', 'interview'], true); ?>
        <tr<?= in_array($r['stage'], ['offered', 'accepted'], true) ? ' style="background:var(--good-soft)"' : '' ?>>
            <?php if ($manage): ?><td><?php if ($canOffer($r)): ?><input type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" aria-label="Offer <?= e($r['first_name']) ?>"><?php endif; ?></td><?php endif; ?>
            <td><strong><?= $r['rank'] ?? '—' ?></strong></td>
            <td><a href="<?= e(url('admission/' . $r['id'])) ?>"><?= e(full_name($r)) ?></a><br><small class="muted"><?= e($r['ref_no']) ?></small></td>
            <td><?php if ($edit): ?><input name="entrance[<?= (int) $r['id'] ?>]" value="<?= e($r['entrance_score'] ?? '') ?>" inputmode="decimal" style="width:5.5rem" aria-label="Entrance score"><?php else: ?><?= e($r['entrance_score'] ?? '—') ?><?php endif; ?></td>
            <td><?php if ($edit): ?><input name="interview[<?= (int) $r['id'] ?>]" value="<?= e($r['interview_score'] ?? '') ?>" inputmode="decimal" style="width:5.5rem" aria-label="Interview score"><?php else: ?><?= e($r['interview_score'] ?? '—') ?><?php endif; ?></td>
            <td class="num"><strong><?= $r['merit'] === null ? '—' : e(Results::fmt($r['merit'])) ?></strong></td>
            <td><?= Admission::badge($r['stage']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7" class="muted">No applicants for this level this year.</td></tr><?php endif; ?>
    </table></div>
    <?php if ($manage && $rows): ?><div style="padding:1rem;display:flex;gap:.5rem;flex-wrap:wrap">
        <button class="btn" type="submit" formaction="<?= e(url('admission/merit/scores')) ?>">Save scores</button>
        <button class="btn btn-primary" type="submit" formaction="<?= e(url('admission/merit/offer')) ?>" data-confirm="Offer places to the ticked applicants? Parents get an SMS.">Offer places to ticked applicants</button>
    </div><?php endif; ?>
</form>
