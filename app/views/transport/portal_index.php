<div class="page-head">
    <div><h1>My transport</h1><p class="muted">Transport details for your student records.</p></div>
</div>
<div class="card">
    <?php if (!$assignments): ?>
        <p class="muted" style="margin:0">No active transport assignments are recorded for your account.</p>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Student</th><th>Class</th><th>Route</th><th>Stop</th><th>Trips</th></tr></thead>
            <tbody><?php foreach ($assignments as $assignment): ?>
                <tr>
                    <td><?= e(trim($assignment['first_name'] . ' ' . $assignment['last_name'])) ?></td>
                    <td><?= e($assignment['class_name'] ?? '—') ?></td>
                    <td><a href="<?= e(url('transport/routes/' . $assignment['route_id'])) ?>"><?= e($assignment['route_name']) ?></a></td>
                    <td><?= e($assignment['stop'] ?? '—') ?></td>
                    <td><?= e(TransportController::TRIPS[$assignment['trips']] ?? $assignment['trips']) ?></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</div>
