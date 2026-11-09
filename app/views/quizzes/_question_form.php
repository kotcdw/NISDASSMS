<?php /** $qq = question or null; $id = quiz id */
$type = $qq['type'] ?? 'mcq'; $opts = $qq ? Quiz::options($qq) : []; while (count($opts) < 4) { $opts[] = ''; } ?>
<form method="post" action="<?= e(url('quizzes/' . $id . '/questions' . ($qq ? '/' . $qq['id'] : ''))) ?>" class="form" data-qtype-form>
    <?= csrf_field() ?>
    <div class="grid-2">
        <label>Type<select name="type" data-qtype><?php foreach (Quiz::TYPES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($type, $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
        <label>Marks<input name="marks" inputmode="decimal" value="<?= e(Quiz::fmt($qq['marks'] ?? 1)) ?>"></label>
    </div>
    <label>Question<textarea name="prompt" rows="2" required maxlength="4000"><?= e($qq['prompt'] ?? '') ?></textarea></label>
    <div data-for="mcq">
        <p class="hint" style="margin:.2rem 0">Choices — tick the correct one:</p>
        <?php foreach ($opts as $i => $o): ?>
        <div style="display:flex;gap:.5rem;align-items:center;margin-bottom:.35rem"><input type="radio" name="correct" value="<?= $i ?>"<?= $qq && $type === 'mcq' && (int) $qq['answer'] === $i ? ' checked' : '' ?> aria-label="Correct"><input name="options[]" maxlength="300" value="<?= e($o) ?>" placeholder="Choice <?= chr(65 + $i) ?>"></div>
        <?php endforeach; ?>
        <div style="display:flex;gap:.5rem;align-items:center;margin-bottom:.35rem"><input type="radio" name="correct" value="<?= count($opts) ?>" aria-label="Correct"><input name="options[]" maxlength="300" placeholder="Choice <?= chr(65 + count($opts)) ?> (optional)"></div>
    </div>
    <div data-for="truefalse">
        <label style="font-weight:400;display:inline-block;margin-right:1rem"><input type="radio" name="tf" value="true"<?= $qq && $qq['answer'] === 'true' ? ' checked' : '' ?>> True</label>
        <label style="font-weight:400;display:inline-block"><input type="radio" name="tf" value="false"<?= $qq && $qq['answer'] === 'false' ? ' checked' : '' ?>> False</label>
    </div>
    <div data-for="short">
        <label>Accepted answer(s) — separate alternatives with |<input name="accepted" maxlength="255" value="<?= e($type === 'short' ? (string) ($qq['answer'] ?? '') : '') ?>" placeholder="e.g. Kumasi|Kumase"></label>
        
    </div>
    <button class="btn<?= $qq ? '' : ' btn-primary' ?>" type="submit"><?= $qq ? 'Save question' : 'Add question' ?></button>
</form>
