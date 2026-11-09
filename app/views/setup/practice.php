<div class="page-head">
    <div><h1>Practice copy</h1></div>
    <?php if ($exists): ?><div class="actions"><a class="btn btn-primary" href="<?= e($demoUrl) ?>" target="_blank">Open the practice copy</a></div><?php endif; ?>
</div>
<?php if (is_demo()): ?><div class="alert alert-warning">You are in the practice copy. To reset it, open the real system → Administration → Practice copy.</div><?php endif; ?>
<div class="cols">
    <div class="card">
        <h2>Practice logins</h2>
        <p class="hint">Every practice login uses the password <code><?= e(DemoSeeder::PASSWORD) ?></code>.</p>
        <div class="table-wrap"><table>
            <tr><th>Role</th><th>Sign in with</th></tr>
            <tr><td>Super Admin</td><td><code>admin@demo.school</code></td></tr>
            <tr><td>Administrator</td><td><code>administrator@demo.school</code></td></tr>
            <tr><td>Head Teacher</td><td><code>head@demo.school</code></td></tr>
            <tr><td>Academic Coordinator</td><td><code>coordinator@demo.school</code></td></tr>
            <tr><td>Bursar</td><td><code>bursar@demo.school</code></td></tr>
            <tr><td>Admissions Officer</td><td><code>admissions@demo.school</code></td></tr>
            <tr><td>Class teachers (Creche → JHS 3)</td><td><code>teacher1@demo.school</code> … <code>teacher12@demo.school</code></td></tr>
            <tr><td>JHS subject teachers</td><td><code>subject1@demo.school</code> … <code>subject4@demo.school</code></td></tr>
            <tr><td>A parent</td><td><code>0200001000</code> (phone number)</td></tr>
            <tr><td>A JHS 1 student (has a Maths quiz to take)</td><td><code>pupil1</code></td></tr>
        </table></div>
        <?php if ($loginGroups): ?>
        <h3>All test accounts</h3>
        <p class="hint">Use the shared practice password shown above. Student and parent sign-ins use their admission number and phone number.</p>
        <?php foreach ($loginGroups as $group => $accounts): ?>
            <details>
                <summary><?= e($group) ?> (<?= count($accounts) ?>)</summary>
                <div class="table-wrap"><table>
                    <tr><th>Name</th><th>Sign in with</th></tr>
                    <?php foreach ($accounts as $account): ?>
                    <tr><td><?= e($account['name']) ?></td><td><code><?= e($account['identifier']) ?></code></td></tr>
                    <?php endforeach; ?>
                </table></div>
            </details>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php if (!is_demo()): ?>
    <form method="post" action="<?= e(url('practice')) ?>" class="card form" data-confirm="<?= $exists ? 'Wipe the practice copy and build a fresh one?' : 'Create the practice copy?' ?>">
        <?= csrf_field() ?>
        <h2><?= $exists ? 'Reset the practice copy' : 'Create the practice copy' ?></h2>
        
        <label>Type PRACTICE to confirm<input name="confirm" autocomplete="off" required></label>
        <button class="btn btn-primary" type="submit"><?= $exists ? 'Reset the practice copy' : 'Create the practice copy' ?></button>
    </form>
    <?php endif; ?>
</div>
<?php if (!is_demo() && $exists): ?>
<form method="post" action="<?= e(url('practice/remove')) ?>" class="card" data-confirm="Remove the practice copy and all practice records?" style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap">
    <?= csrf_field() ?>
    <div style="flex:1 1 300px"><strong>Training finished?</strong><br><small class="muted">Remove the practice copy. On internet hosting, remove it after training (its logins are printed here) or set <code>'practice_copy' => false</code> in config.local.php.</small></div>
    <button class="btn btn-danger" type="submit">Remove the practice copy</button>
</form>
<?php endif; ?>
