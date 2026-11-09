<?php $ov = fn($k, $d = '') => old($k, $d); ?>
<?php if ($preview): ?><div class="alert alert-warning">Preview — this form is <?= e(FormDef::STATUSES[$form['status']][0]) ?> and is not accepting responses.</div><?php endif; ?>
<h1><?= e($form['title']) ?></h1>
<?php if ($form['description']): ?><p class="muted" style="white-space:pre-line"><?= e($form['description']) ?></p><?php endif; ?>

<?php if (!$open && !$preview): ?>
    <div class="card"><p>This form is not accepting responses at the moment.</p></div>
<?php else: ?>
<div class="card">
    <form method="post" action="<?= e(url('f/' . $form['public_key'])) ?>" enctype="multipart/form-data" class="form">
        <?= csrf_field() ?>
        <div style="position:absolute;left:-5000px" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
        <?php if ($form['collect_contact'] && $form['audience'] === 'public'): ?>
        <div class="grid-2">
            <label class="required">Your name <input name="respondent_name" required maxlength="150" value="<?= e($ov('respondent_name')) ?>"><?= field_error('respondent_name') ?></label>
            <label class="required">Your phone <input name="respondent_phone" required placeholder="0244123456" value="<?= e($ov('respondent_phone')) ?>"><?= field_error('respondent_phone') ?></label>
        </div>
        <?php endif; ?>

        <?php foreach ($fields as $f): $id = (int) $f['id']; $name = "f[$id]"; $val = $ov('f' . $id, $f['type'] === 'checkbox' ? [] : ''); $req = $f['is_required'] ? ' required' : ''; $cls = $f['is_required'] ? ' class="required"' : ''; ?>
            <?php if ($f['type'] === 'section'): ?>
                <h2 style="margin-top:1.25rem"><?= e($f['label']) ?></h2>
                <?php if ($f['help_text']): ?><p class="muted"><?= e($f['help_text']) ?></p><?php endif; ?>
                <?php continue; ?>
            <?php endif; ?>

            <?php if (in_array($f['type'], ['radio', 'checkbox'], true)): ?>
                <fieldset>
                    <legend><?= e($f['label']) ?><?= $f['is_required'] ? ' <span style="color:var(--bad)">*</span>' : '' ?></legend>
                    <?php if ($f['help_text']): ?><p class="hint" style="margin:0 0 .5rem"><?= e($f['help_text']) ?></p><?php endif; ?>
                    <?php foreach (FormDef::options($f['options']) as $opt): ?>
                        <label style="font-weight:400;margin-bottom:.4rem">
                            <?php if ($f['type'] === 'radio'): ?>
                                <input type="radio" name="<?= $name ?>" value="<?= e($opt) ?>"<?= checked($val === $opt) ?><?= $req ?>>
                            <?php else: ?>
                                <input type="checkbox" name="<?= $name ?>[]" value="<?= e($opt) ?>"<?= checked(in_array($opt, (array) $val, true)) ?>>
                            <?php endif; ?>
                            <?= e($opt) ?>
                        </label>
                    <?php endforeach; ?>
                    <?= field_error('f' . $id) ?>
                </fieldset>
                <?php continue; ?>
            <?php endif; ?>

            <label<?= $cls ?>><?= e($f['label']) ?>
                <?php switch ($f['type']):
                    case 'textarea': ?><textarea name="<?= $name ?>" rows="4" maxlength="5000"<?= $req ?>><?= e($val) ?></textarea><?php break;
                    case 'select': ?><select name="<?= $name ?>"<?= $req ?>><option value="">— choose —</option><?php foreach (FormDef::options($f['options']) as $opt): ?><option<?= selected($opt, $val) ?>><?= e($opt) ?></option><?php endforeach; ?></select><?php break;
                    case 'file': ?><input type="file" name="<?= $name ?>" accept=".pdf,.jpg,.jpeg,.png"<?= $req ?>><?php break;
                    case 'number': ?><input type="number" step="any" name="<?= $name ?>" value="<?= e($val) ?>"<?= $req ?>><?php break;
                    case 'email': ?><input type="email" name="<?= $name ?>" value="<?= e($val) ?>"<?= $req ?>><?php break;
                    case 'phone': ?><input type="tel" name="<?= $name ?>" placeholder="0244123456" value="<?= e($val) ?>"<?= $req ?>><?php break;
                    case 'date': ?><input type="date" name="<?= $name ?>" value="<?= e($val) ?>"<?= $req ?>><?php break;
                    default: ?><input name="<?= $name ?>" maxlength="500" value="<?= e($val) ?>"<?= $req ?>>
                <?php endswitch; ?>
                <?= field_error('f' . $id) ?>
            </label>
            <?php if ($f['help_text']): ?><p class="hint"><?= e($f['help_text']) ?></p><?php endif; ?>
        <?php endforeach; ?>

        <?php if ($open): ?><button class="btn btn-primary" type="submit">Submit</button><?php endif; ?>
    </form>
</div>
<?php endif; ?>
