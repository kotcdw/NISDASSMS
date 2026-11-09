<?php
$mg = can('curriculum.manage');
$e = $edit ?? [];
$v = fn($k, $d = '') => old($k, (string) ($e[$k] ?? $d));
// The form opens from the button; it starts open when editing or when a save came back with an error
$openForm = $mg && ($e || old('title') !== '');
?>
<div class="xgrid-box" data-xgrid="materials" data-xgrid-file="textbooks-materials">
<div class="page-head">
    <div><h1>Textbooks &amp; materials</h1></div>
    <div class="actions">
        <form method="get" action="<?= e(url('curriculum/materials')) ?>" class="list-pick" data-autosubmit>
            <?php if ($levelId): ?><input type="hidden" name="level" value="<?= (int) $levelId ?>"><?php endif; ?>
            <select name="subject" aria-label="Subject"><option value="0">All subjects</option><?php foreach ($subjects as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected($s['id'], $subjectId) ?>><?= e($s['name']) ?></option><?php endforeach; ?></select>
        </form>
        <?= export_grid_menu() ?>
        <?php if ($mg): ?><button type="button" class="btn btn-primary" data-help="material-form" aria-controls="material-form" aria-expanded="<?= $openForm ? 'true' : 'false' ?>">+ Add a book or material</button><?php endif; ?>
    </div>
</div>
<?php if ($mg): ?>
<form method="post" action="<?= e(url($e ? 'curriculum/materials/' . $e['id'] : 'curriculum/materials')) ?>" class="card form list-form" id="material-form"<?= $openForm ? '' : ' hidden' ?>>
    <?= csrf_field() ?><input type="hidden" name="f_subject" value="<?= (int) $subjectId ?>"><input type="hidden" name="f_level" value="<?= (int) $levelId ?>">
    <h2><?= $e ? 'Edit “' . e($e['title']) . '”' : 'Add a book or material' ?></h2>
    <div class="grid-2">
        <label class="required">Subject<select name="subject_id" required><option value="">Choose…</option><?php foreach ($subjects as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected($s['id'], $v('subject_id', (string) $subjectId)) ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></label>
        <label>Class level<select name="level_id"><option value="">All levels</option><?php foreach ($levels as $l): ?><option value="<?= (int) $l['id'] ?>"<?= selected($l['id'], $v('level_id', (string) $levelId)) ?>><?= e($l['name']) ?></option><?php endforeach; ?></select></label>
    </div>
    <label class="required">Title<input name="title" maxlength="200" required value="<?= e($v('title')) ?>" placeholder="e.g. Mathematics for Basic 4 Learner's Book"></label>
    <div class="grid-2">
        <label>Type<select name="kind"><?php foreach (SubjectController::KINDS as $k => $l): ?><option value="<?= $k ?>"<?= selected($k, $v('kind', 'textbook')) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
        <label>Approved / supplied<select name="approved_by"><?php foreach (SubjectController::APPROVERS as $k => $l): ?><option value="<?= $k ?>"<?= selected($k, $v('approved_by', 'nacca')) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
        <label>Author / publisher<input name="author_publisher" maxlength="200" value="<?= e($v('author_publisher')) ?>"></label>
        <label>ISBN<input name="isbn" maxlength="20" value="<?= e($v('isbn')) ?>" placeholder="Links it to library copies"></label>
        <label>Copies held (outside the library)<input type="number" name="copies_held" min="0" max="9999" value="<?= e($v('copies_held', '0')) ?>"></label>
        <label style="align-self:end;font-weight:400"><input type="checkbox" name="per_student" value="1"<?= checked($v('per_student', '1') === '1') ?>> One copy per student</label>
    </div>
    <label>Notes<input name="notes" maxlength="255" value="<?= e($v('notes')) ?>"></label>
    <div class="gate-actions"><button class="btn btn-primary" type="submit"><?= $e ? 'Save changes' : 'Add to the list' ?></button><?php if ($e): ?><a class="btn btn-ghost" href="<?= e(url('curriculum/materials', array_filter(['subject' => $subjectId, 'level' => $levelId]))) ?>">Cancel</a><?php else: ?><button type="button" class="btn btn-ghost" data-help="material-form">Cancel</button><?php endif; ?></div>
</form>
<?php endif; ?>
    <div class="xg-scroll">
    <table class="xgrid">
        <thead><tr>
            <?php foreach (['subject' => ['Subject', 'text'], 'level' => ['Level', 'text'], 'kind' => ['Type', 'text'], 'title' => ['Title', 'text'], 'author' => ['Author / publisher', 'text'], 'appr' => ['Approved', 'text'], 'held' => ['Copies held', 'num'], 'short' => ['Short', 'num']] as $k => [$t, $ty]): ?><th data-c="<?= $k ?>" data-type="<?= $ty ?>"><button type="button" class="xg-sort"><?= e($t) ?><span class="xg-arrow"></span></button></th><?php endforeach; ?>
            <?php if ($mg): ?><th class="xg-act"></th><?php endif; ?>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $m): ?>
        <tr>
            <td data-c="subject"><?= e($m['subject_name']) ?></td>
            <td data-c="level" data-s="<?= (int) ($m['sort_order'] ?? 0) ?>"><?= e($m['level_name'] ?? 'All levels') ?></td>
            <td data-c="kind"><?= e(SubjectController::KINDS[$m['kind']] ?? $m['kind']) ?></td>
            <td data-c="title"><strong><?= e($m['title']) ?></strong><?= $m['isbn'] ? '<span hidden> · </span> <small class="muted">ISBN ' . e($m['isbn']) . '</small>' : '' ?></td>
            <td data-c="author"><?= e($m['author_publisher'] ?? '') ?></td>
            <td data-c="appr"><?= badge(SubjectController::APPROVERS[$m['approved_by']] ?? '', $m['approved_by'] === 'school' ? 'neutral' : 'good') ?></td>
            <td data-c="held" class="num" title="<?= (int) $m['copies_held'] ?> recorded + <?= (int) $m['library'] ?> in the library"><?= (int) $m['held'] ?><?= $m['needed'] !== null ? ' <small class="muted">of ' . (int) $m['needed'] . '</small>' : '' ?></td>
            <td data-c="short" class="num"><?= $m['short'] ? '<span class="short">' . (int) $m['short'] . '</span>' : '<span class="muted">—</span>' ?></td>
            <?php if ($mg): ?><td class="xg-act"><details class="rowmenu"><summary aria-label="Actions">⋯</summary><div>
                <a href="<?= e(url('curriculum/materials', array_filter(['subject' => $subjectId, 'level' => $levelId, 'edit' => $m['id']]))) ?>#material-form">Edit</a>
                <form method="post" action="<?= e(url('curriculum/materials/' . $m['id'] . '/delete')) ?>" data-confirm="Remove “<?= e($m['title']) ?>” from the list?" data-confirm-ok="Remove" data-confirm-tone="danger"><?= csrf_field() ?><button type="submit" class="danger">Remove</button></form>
            </div></details></td><?php endif; ?>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No books or materials listed yet.</p>
    </div>
</div>
