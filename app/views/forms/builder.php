<?php
[$sl, $st] = FormDef::STATUSES[$form['status']];
$link = FormDef::publicUrl($form);
$isLocal = preg_match('#//(localhost|127\.|192\.168\.|10\.)#', $link);
?>
<div class="page-head">
    <div>
        <div class="breadcrumb"><a href="<?= e(url('forms')) ?>">Form Builder</a> /</div>
        <h1><?= e($form['title']) ?> <?= badge($sl, $st) ?></h1>
    </div>
    <div class="actions">
        <a class="btn" href="<?= e(url('f/' . $form['public_key'])) ?>" target="_blank" rel="noopener"><?= FormDef::isOpen($form) ? 'Open form' : 'Preview' ?></a>
        <form method="post" action="<?= e(url('forms/' . $form['id'] . '/duplicate')) ?>"><?= csrf_field() ?><button class="btn btn-ghost" type="submit">Duplicate</button></form>
    </div>
</div>

<?php if (FormDef::isOpen($form)): ?>
<div class="alert alert-success">
    Form link: <strong><?= e($link) ?></strong>
    <?php if ($isLocal): ?><br><small>This address only works on the school network. Parents can open it from home once the system is hosted online.</small><?php endif; ?>
</div>
<?php endif; ?>

<div class="cols">
    <div>
        <h2>Questions</h2>
        <?php if (!$fields): ?><p class="muted">No questions yet — add the first one below.</p><?php endif; ?>
        <?php foreach ($fields as $i => $f): $fid = (int) $f['id']; ?>
        <div class="field-row">
            <div class="grow">
                <details>
                    <summary style="cursor:pointer">
                        <strong><?= $i + 1 ?>. <?= e($f['label']) ?></strong><?= $f['is_required'] ? ' <span style="color:var(--bad)">*</span>' : '' ?>
                        <small> — <?= e(FormDef::TYPES[$f['type']]) ?><?= isset($answered[$fid]) ? ' · has answers' : '' ?></small>
                        <?php if (in_array($f['type'], FormDef::CHOICE_TYPES, true)): ?><br><small class="muted"><?= e(implode(' · ', FormDef::options($f['options']))) ?></small><?php endif; ?>
                    </summary>
                    <form method="post" action="<?= e(url('forms/' . $form['id'] . '/fields/' . $fid)) ?>" class="form" style="margin-top:.75rem">
                        <?= csrf_field() ?>
                        <?php $field = $f; require __DIR__ . '/_field_inputs.php'; ?>
                        <button class="btn btn-sm btn-primary" type="submit">Save question</button>
                    </form>
                </details>
            </div>
            <div class="tools">
                <form method="post" action="<?= e(url('forms/' . $form['id'] . '/fields/' . $fid . '/move')) ?>"><?= csrf_field() ?><input type="hidden" name="dir" value="up"><button class="btn btn-sm btn-ghost" type="submit" title="Move up"<?= $i === 0 ? ' disabled' : '' ?>>↑</button></form>
                <form method="post" action="<?= e(url('forms/' . $form['id'] . '/fields/' . $fid . '/move')) ?>"><?= csrf_field() ?><input type="hidden" name="dir" value="down"><button class="btn btn-sm btn-ghost" type="submit" title="Move down"<?= $i === count($fields) - 1 ? ' disabled' : '' ?>>↓</button></form>
                <form method="post" action="<?= e(url('forms/' . $form['id'] . '/fields/' . $fid . '/delete')) ?>" data-confirm="Remove this question?"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" type="submit" title="Remove">✕</button></form>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="card">
            <h2>Add a question</h2>
            <form method="post" action="<?= e(url('forms/' . $form['id'] . '/fields')) ?>" class="form">
                <?= csrf_field() ?>
                <?php $field = null; require __DIR__ . '/_field_inputs.php'; ?>
                <button class="btn btn-primary" type="submit">Add question</button>
            </form>
        </div>
    </div>

    <div>
        <div class="card">
            <h2>Settings</h2>
            <form method="post" action="<?= e(url('forms/' . $form['id'])) ?>" class="form">
                <?= csrf_field() ?>
                <label class="required">Title <input name="title" required maxlength="150" value="<?= e($form['title']) ?>"></label>
                <label>Introduction <textarea name="description" rows="3" maxlength="2000"><?= e($form['description']) ?></textarea></label>
                <label>Status
                    <select name="status">
                        <?php foreach (FormDef::STATUSES as $k => [$label]): ?><option value="<?= $k ?>"<?= selected($k, $form['status']) ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label>Who fills it
                    <select name="audience"><option value="public"<?= selected('public', $form['audience']) ?>>Parents / public</option><option value="staff"<?= selected('staff', $form['audience']) ?>>Staff (sign-in)</option></select>
                </label>
                <label>Closes on (optional) <input type="date" name="closes_on" value="<?= e($form['closes_on']) ?>"></label>
                <label><input type="checkbox" name="collect_contact" value="1"<?= checked($form['collect_contact']) ?>> Ask for name and phone (parents)</label>
                <label><input type="checkbox" name="one_per_phone" value="1"<?= checked($form['one_per_phone']) ?>> One response per phone number</label>
                <label>Thank-you message <input name="confirmation_message" maxlength="255" value="<?= e($form['confirmation_message']) ?>"></label>
                <button class="btn btn-primary" type="submit">Save settings</button>
            </form>
        </div>
        <?php if ($audiences && FormDef::isOpen($form) && $form['audience'] === 'public'): ?>
        <div class="card">
            <h2>Send link by SMS</h2>
            <form method="post" action="<?= e(url('forms/' . $form['id'] . '/share')) ?>" class="form" data-confirm="Send this form link by SMS?">
                <?= csrf_field() ?>
                <label>To
                    <select name="audience"><?php foreach ($audiences as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?></select>
                </label>
                <label>Phone numbers (for “Type phone numbers”) <input name="custom" placeholder="0244123456, 0201234567"></label>
                <label>Message (the link is added at the end) <textarea name="intro" rows="3" maxlength="300" data-sms-counter><?= e(school_name() . ': please complete "' . $form['title'] . '":') ?></textarea></label>
                <button class="btn" type="submit">Send link</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
