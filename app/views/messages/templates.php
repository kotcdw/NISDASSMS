<?php require __DIR__ . '/_tabs.php'; ?>
<?php $short = fn(string $s, int $n = 70) => mb_strlen($s) > $n ? rtrim(mb_substr($s, 0, $n - 1)) . '…' : $s; ?>
<div class="page-center wide">
<div class="card" style="padding:0">
    <div class="table-wrap"><table>
        <thead><tr><th>Template</th><th>Message</th><th>Status</th><th class="xg-act"></th></tr></thead>
        <tbody>
        <?php foreach ($templates as $t): $k = 'edit-tpl-' . preg_replace('/[^\w-]/', '-', (string) $t['key']); ?>
        <tr>
            <td><strong><?= e($t['name']) ?></strong></td>
            <td style="min-width:14rem"><span class="muted"><?= e($short((string) $t['sms_body'])) ?></span></td>
            <td><?= (int) $t['is_active'] ? badge('On', 'good') : badge('Off') ?></td>
            <td class="xg-act"><button type="button" class="btn btn-sm" data-help="<?= e($k) ?>" aria-expanded="false">Edit</button></td>
        </tr>
        <tr id="<?= e($k) ?>" class="xg-edit" hidden>
            <td colspan="4">
                <form method="post" action="<?= e(url('messages/templates')) ?>" class="form" style="margin:.2rem 0 .4rem">
                    <?= csrf_field() ?>
                    <input type="hidden" name="key" value="<?= e($t['key']) ?>">
                    <?php if ($t['description']): ?><p class="hint" style="margin:0 0 .5rem"><?= e($t['description']) ?></p><?php endif; ?>
                    <label style="font-weight:400"><input type="checkbox" name="is_active" value="1"<?= checked($t['is_active']) ?>> On (send this message)</label>
                    <label>Message<textarea name="sms_body" rows="3" maxlength="480" data-sms-counter><?= e($t['sms_body']) ?></textarea></label>
                    <p class="hint" style="margin-top:.3rem">Placeholders: <?= e($t['placeholders']) ?></p>
                    <div class="gate-actions"><button class="btn btn-primary" type="submit">Save</button><button type="button" class="btn btn-ghost" data-help="<?= e($k) ?>">Cancel</button></div>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php if (!$templates): ?><p class="muted" style="padding:1rem">No templates yet.</p><?php endif; ?>
</div>
</div>
