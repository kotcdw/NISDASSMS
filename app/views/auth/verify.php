<?php require __DIR__ . '/_top.php'; ?>
    <h2 style="margin:0 0 .4rem">2-step sign-in</h2>
    <p class="muted" style="margin-top:0">Open the authenticator app on your phone and type the 6-digit code for <?= e(school_name()) ?>.</p>
    <form method="post" action="<?= e(url('login/verify')) ?>" class="form">
        <?= csrf_field() ?>
        <label>6-digit code <input type="text" name="code" required autofocus inputmode="numeric" autocomplete="one-time-code" maxlength="11" pattern="[0-9 A-Za-z-]{6,11}" style="font-size:1.4rem;letter-spacing:.3em;text-align:center"></label>
        <button class="btn btn-primary" type="submit" style="width:100%;justify-content:center">Continue</button>
    </form>
    <details style="margin-top:1rem"><summary class="hint" style="cursor:pointer">Lost your phone?</summary>
        <p class="hint">Type one of your printed recovery codes (like ABCDE-FGH23) in the box instead. Each works once. If you have none, ask the Super Admin to reset your 2-step sign-in.</p>
    </details>
    <p style="margin-top:1rem"><a href="<?= e(url('login')) ?>">← Start again</a></p>
</main>
</div>
</body>
</html>
