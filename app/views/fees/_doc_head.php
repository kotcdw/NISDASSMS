<header class="rc-head">
    <img class="rc-logo-img" src="<?= e(logo_url()) ?>" alt="">
    <div class="rc-school">
        <h1><?= e(school_name()) ?></h1>
        <?php if (setting('school_motto')): ?><p class="motto"><?= e(setting('school_motto')) ?></p><?php endif; ?>
        <p><?= e(implode(' · ', array_filter([setting('school_address'), setting('school_phone'), setting('school_email')]))) ?></p>
        <?php if (setting('school_tin')): ?><p>GRA TIN: <?= e(setting('school_tin')) ?></p><?php endif; ?>
    </div>
    <div class="rc-title"><strong><?= e($docTitle) ?></strong><span><?= e($docSub ?? '') ?></span></div>
</header>
