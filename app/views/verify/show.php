<div class="card form-center" style="max-width:560px">
    <h1 style="margin-top:0">Check a receipt or voucher</h1>
    <?php if ($tried && $d): ?>
        <div class="alert alert-<?= $d['ok'] ? 'success' : 'error' ?>" style="font-size:1.05rem"><?= $d['ok'] ? '✓ Genuine document from ' . e(school_name()) : '⚠ This document is no longer valid' ?></div>
        <table>
            <tr><th>Document</th><td><?= e($d['type']) ?> <strong><?= e($d['no']) ?></strong></td></tr>
            <tr><th>Date</th><td><?= e(fmt_date((string) $d['date'])) ?></td></tr>
            <tr><th>Amount</th><td><strong><?= e(Fees::money($d['amount'])) ?></strong></td></tr>
            <tr><th>For</th><td><?= e($d['for']) ?></td></tr>
            <tr><th>Status</th><td><?= e($d['status']) ?></td></tr>
        </table>
        <p class="hint">Check that the number, date and amount match the paper. If they do not, call the school<?= setting('school_phone') ? ' on ' . e(setting('school_phone')) : '' ?>.</p>
    <?php elseif ($tried): ?>
        <div class="alert alert-error">No document matches this code. The paper may not be genuine — please call the school<?= setting('school_phone') ? ' on ' . e(setting('school_phone')) : '' ?>.</div>
    <?php endif; ?>
    <form method="get" action="<?= e(url('verify')) ?>" class="form" style="margin-top:1rem">
        <label>Check code printed under the QR code (10 letters/numbers)<input name="code" maxlength="12" value="<?= e($code) ?>" autocomplete="off" required></label>
        <button class="btn btn-primary" type="submit">Check</button>
    </form>
</div>
