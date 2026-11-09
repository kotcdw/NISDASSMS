<h1>Check application</h1>
<?php if ($app): ?>
<div class="card">
    <dl class="details">
        <dt>Reference</dt><dd><?= e($app['ref_no']) ?></dd>
        <dt>Child</dt><dd><?= e($app['first_name'] . ' ' . $app['last_name']) ?></dd>
        <dt>Class applied for</dt><dd><?= e($app['level_name']) ?></dd>
        <dt>Received</dt><dd><?= e(fmt_date($app['created_at'])) ?></dd>
        <dt>Status</dt><dd><?= Admission::badge($app['stage']) ?></dd>
        <?php if ($app['interview_date'] && in_array($app['stage'], ['screening', 'interview'], true)): ?>
            <dt>Assessment / interview</dt><dd><?= e(fmt_date($app['interview_date'], 'l j F Y')) ?></dd>
        <?php endif; ?>
    </dl>
</div>
<?php endif; ?>
<div class="card" style="max-width:520px">
    <form method="post" action="<?= e(url('apply/status')) ?>" class="form">
        <?= csrf_field() ?>
        <label>Reference number <input name="ref_no" required placeholder="APP-2026-0001"></label>
        <label>Parent / guardian phone <input name="phone" required placeholder="0244123456"></label>
        <button class="btn btn-primary" type="submit">Check status</button>
    </form>
</div>
