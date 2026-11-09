<?php
/* Grading setup: one tab per stage (Creche, KG, Basic, JHS) plus Report card. Expects $schemes (with bands and level_bands). */
$short = ['creche' => 'Creche', 'kg' => 'Kindergarten', 'primary' => 'Basic 1–6', 'jhs' => 'JHS'];
$first = $schemes[0]['category'] ?? 'report';
$sv = fn(string $d) => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
/** One editable band table (grades or levels); spare rows are revealed by "+ Add a row". */
$bandTable = function (string $id, string $field, array $rows, array $heads) use ($sv): string {
    $h = '<div class="table-wrap"><table class="band-table" id="' . e($id) . '"><thead><tr><th>' . e($heads[0]) . '</th><th>' . e($heads[1]) . '</th><th>' . e($heads[2]) . '</th><th class="bt-x"><span class="sr-only">Remove</span></th></tr></thead><tbody>';
    $n = count($rows) + 4;
    for ($i = 0; $i < $n; $i++) {
        $b = $rows[$i] ?? null;
        $h .= '<tr' . ($b ? '' : ' hidden') . '>'
            . '<td class="bt-min"><span class="bt-from"><input type="number" name="' . $field . '[' . $i . '][min]" min="0" max="100" step="0.5" value="' . ($b ? e((float) $b['min_score']) : '') . '" aria-label="' . e($heads[0]) . '"><span class="muted">and above</span></span></td>'
            . '<td class="bt-grade"><input name="' . $field . '[' . $i . '][grade]" maxlength="10" value="' . e($b['grade'] ?? '') . '" aria-label="' . e($heads[1]) . '"></td>'
            . '<td><input name="' . $field . '[' . $i . '][remark]" maxlength="60" value="' . e($b['remark'] ?? '') . '" aria-label="' . e($heads[2]) . '"></td>'
            . '<td class="bt-x"><button type="button" class="icon-btn" data-band-del title="Remove this row" aria-label="Remove this row">' . $sv('<path d="M18 6 6 18M6 6l12 12"/>') . '</button></td></tr>';
    }
    return $h . '</tbody></table></div><button type="button" class="btn btn-ghost btn-sm bt-add" data-band-add="' . e($id) . '">+ Add a row</button>';
};
$toggle = fn(string $name, bool $on, string $title, string $hint, string $id = '') => '<label class="mod-row"><span class="mod-text"><strong>' . e($title) . '</strong><small class="muted">' . e($hint) . '</small></span><span class="toggle"><input type="checkbox" name="' . e($name) . '" value="1"' . ($id ? ' id="' . e($id) . '"' : '') . ($on ? ' checked' : '') . '><span class="toggle-ui" aria-hidden="true"></span></span></label>';
?>
<div class="page-head">
    <div><h1>Grading setup</h1></div>
</div>

<div class="card ptabs gs" data-ptabs style="padding:0">
    <div class="ptab-bar" role="tablist" aria-label="Grading setup">
        <?php foreach ($schemes as $s): $c = $s['category']; ?>
        <button type="button" role="tab" id="pt-<?= e($c) ?>" aria-controls="pp-<?= e($c) ?>" aria-selected="<?= $c === $first ? 'true' : 'false' ?>"<?= $c === $first ? '' : ' tabindex="-1"' ?> data-ptab="<?= e($c) ?>"><?= e($short[$c] ?? $s['name']) ?></button>
        <?php endforeach; ?>
        <button type="button" role="tab" id="pt-report" aria-controls="pp-report" aria-selected="false" tabindex="-1" data-ptab="report">Report card</button>
    </div>

    <?php foreach ($schemes as $s): $c = $s['category']; $sid = (int) $s['id']; $label = $short[$c] ?? $s['name']; ?>
    <section class="ptab-panel gs-panel" role="tabpanel" id="pp-<?= e($c) ?>" aria-labelledby="pt-<?= e($c) ?>" data-ppanel="<?= e($c) ?>"<?= $c === $first ? '' : ' hidden' ?>>
        <div class="gs-head">
            <div><h2><?= e($label) ?></h2><p class="muted"><?= e($s['name']) ?></p></div>
            <details class="menu-btn">
                <summary class="btn btn-ghost gs-more">More <?= $sv('<path d="m6 9 6 6 6-6"/>') ?></summary>
                <div>
                    <form method="post" action="<?= e(url('exams/setup/' . $sid . '/preset')) ?>" data-confirm="Set <?= e($label) ?> to class 50% + exams 50% with grades 1–9 (90–100 = 1 Excellent … 0–39 = 9 Fail), as on the school's printed report?">
                        <?= csrf_field() ?><button type="submit" class="menu-item gs-preset"><strong>Reset to the school's report grading</strong><small>Class 50% + exams 50%, grades 1–9</small></button>
                    </form>
                </div>
            </details>
        </div>

        <form method="post" action="<?= e(url('exams/setup/' . $sid)) ?>" class="form gs-form">
            <?= csrf_field() ?>
            <div class="gs-grid">
                <fieldset class="gs-block" data-weights>
                    <legend>Score weighting</legend>
                    <div class="gs-weights">
                        <label>Class score<span class="gs-pct"><input type="number" name="class_weight" min="0" max="100" value="<?= (int) $s['class_weight'] ?>"><span>%</span></span></label>
                        <label>Exam<span class="gs-pct"><input type="number" name="exam_weight" min="0" max="100" value="<?= (int) $s['exam_weight'] ?>"><span>%</span></span></label>
                        <label>Pass mark<span class="gs-pct"><input type="number" name="pass_mark" min="0" max="100" step="0.5" value="<?= e((float) $s['pass_mark']) ?>"><span>/100</span></span></label>
                    </div>
                    <div class="gs-bar" aria-hidden="true"><span class="gs-bar-c" data-bar-class></span><span class="gs-bar-x" data-bar-exam></span></div>
                    <p class="gs-legend muted"><span class="dot dot-c"></span>Class score <span class="dot dot-x"></span>Exam · always adds up to 100%</p>
                </fieldset>
                <fieldset class="gs-block">
                    <legend>On the report card</legend>
                    <?= $toggle('show_position', (bool) $s['show_position'], 'Position in class', 'e.g. 3rd out of 32') ?>
                    <?= $toggle('use_aggregate', (bool) $s['use_aggregate'], 'Aggregate', 'Sum of the best grades (as for BECE)') ?>
                    <?= $toggle('show_levels', (bool) $s['show_levels'], 'NaCCA proficiency level', 'A level (HP, P, AP, D, E) beside each grade') ?>
                </fieldset>
            </div>

            <div class="gs-bands" data-ptabs data-ptabs-local>
                <div class="gs-seg" role="tablist" aria-label="Bands">
                    <button type="button" role="tab" aria-selected="true" data-ptab="g<?= $sid ?>">Grades <span class="ptab-n"><?= count($s['bands']) ?></span></button>
                    <button type="button" role="tab" aria-selected="false" tabindex="-1" data-ptab="l<?= $sid ?>">Proficiency levels <span class="ptab-n"><?= count($s['level_bands']) ?></span></button>
                </div>
                <div role="tabpanel" data-ppanel="g<?= $sid ?>">
                    <?= $bandTable('bands-' . $sid, 'bands', $s['bands'], ['Score from', 'Grade', 'Remark']) ?>
                </div>
                <div role="tabpanel" data-ppanel="l<?= $sid ?>" hidden>
                    <?= $bandTable('levels-' . $sid, 'levels', $s['level_bands'], ['Score from', 'Level', 'Description']) ?>
                </div>
            </div>

            <div class="gs-foot"><span class="muted">The lowest row must start at 0 so every score gets a grade.</span><button class="btn btn-primary" type="submit">Save <?= e($label) ?></button></div>
        </form>
    </section>
    <?php endforeach; ?>

    <section class="ptab-panel gs-panel" role="tabpanel" id="pp-report" aria-labelledby="pt-report" data-ppanel="report" hidden>
        <div class="gs-head"><div><h2>Report card</h2><p class="muted">What appears on every printed report card</p></div></div>
        <form method="post" action="<?= e(url('exams/setup/report')) ?>" class="form gs-form">
            <?= csrf_field() ?>
            <div class="gs-grid">
                <fieldset class="gs-block">
                    <legend>Show on the report</legend>
                    <?= $toggle('report_overall_position', setting('report_overall_position', '0') === '1', 'Overall position', 'Position across all subjects in the class') ?>
                    <?= $toggle('fee_show_on_report', setting('fee_show_on_report', '0') === '1', 'Fee arrears', 'Amount owed, printed at the bottom') ?>
                    <?= $toggle('report_personality', setting('report_personality', '1') === '1', "Page 2: Personality development", "The child's personality traits, rated by the class teacher", 'rp-page2') ?>
                </fieldset>
                <fieldset class="gs-block" data-show-when="rp-page2">
                    <legend>Personality traits</legend>
                    <label><span class="muted" style="font-weight:400">One per line, up to 20</span>
                        <textarea name="personality_traits" rows="11"><?= e(implode("\n", ReportController::traits())) ?></textarea></label>
                </fieldset>
            </div>
            <div class="gs-foot"><span></span><button class="btn btn-primary" type="submit">Save report card</button></div>
        </form>
    </section>
</div>
