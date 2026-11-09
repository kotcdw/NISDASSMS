<div class="card empty">
    <h1>Application received</h1>
    <?php if ($ref): ?>
        <p>Your reference number is</p>
        <p style="font-size:1.8rem;font-weight:700;letter-spacing:.04em"><?= e($ref) ?></p>
        <p class="muted">Write it down. Use it with your phone number to <a href="<?= e(url('apply/status')) ?>">check the progress</a> of your application. The school will contact you about the entrance assessment or interview.</p>
    <?php else: ?>
        <p class="muted">Thank you. The school will contact you.</p>
    <?php endif; ?>
</div>
