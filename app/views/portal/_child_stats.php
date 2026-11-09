<?php $pct = Attendance::percent($child['att_present'], $child['att_marked']); ?>
<div class="child-grid">
    <?php if (module_on('attendance')): ?><div class="stat"><div class="num"><?= $pct === null ? '—' : e((string) $pct) . '%' ?></div><div class="label">Attendance this term<?= $child['att_marked'] ? ' (' . $child['att_present'] . '/' . $child['att_marked'] . ' days)' : '' ?></div></div><?php endif; ?>
    <?php if (module_on('fees')): ?><div class="stat"><div class="num" style="color:<?= $child['balance'] > 0 ? 'var(--bad)' : 'var(--good)' ?>"><?= e(Fees::money(max(0, $child['balance']))) ?></div><div class="label">School fees owed</div>
        <?php if ($child['owing']): ?><a href="<?= e(url('pay/' . $child['owing']['public_key'])) ?>">View bill<?= PaymentGateway::active() !== 'none' ? ' &amp; pay' : '' ?> →</a><?php endif; ?></div><?php endif; ?>
    <?php if (module_on('exams')): ?><div class="stat"><div class="num" style="font-size:1rem;padding-top:.3rem"><?= $child['latest_report'] ? e($child['latest_report']['name'] . ' ' . $child['latest_report']['year_name']) : '—' ?></div><div class="label">Latest report card</div>
        <?php if ($child['latest_report']): ?><a href="<?= e(url('r/' . $child['latest_report']['token'])) ?>" target="_blank" rel="noopener">Open report card →</a><?php endif; ?></div><?php endif; ?>
</div>
