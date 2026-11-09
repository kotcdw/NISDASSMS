<?php /** Warning when SMS links would not open on parents' phones (no or local Public web address). See public_url_status(). */ if (($pu = public_url_status()) && $pu[0] === 'bad'): ?>
<div class="alert alert-warning"><?= e($pu[1]) ?></div>
<?php endif; ?>
