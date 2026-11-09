<?php
/** One A4 page per pupil. $only = a single pupil id, or 0 for the whole class. */
$list = $only ? array_values(array_filter($R['pupils'], fn($p) => (int) $p['id'] === $only)) : $R['pupils'];
if (!$list): ?>
    <p style="padding:2rem">No report found.</p>
<?php endif;
foreach ($list as $p) {
    require __DIR__ . '/_card.php';
}
