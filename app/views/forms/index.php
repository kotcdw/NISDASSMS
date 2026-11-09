<div class="xgrid-box" data-xgrid="forms" data-xgrid-file="forms">
<div class="page-head">
    <div><h1>Form Builder</h1></div>
    <div class="actions"><?= export_grid_menu() ?><button type="button" class="btn btn-primary" data-help="new-form" aria-expanded="false">+ New form</button></div>
</div>
<div id="new-form" class="card" hidden>
    <h2>New form</h2>
    <form method="post" action="<?= e(url('forms')) ?>" class="form">
        <?= csrf_field() ?>
        <label class="required">Title <input name="title" required maxlength="150" placeholder="e.g. Excursion consent — Basic 5"></label>
        <label>Who fills it
            <select name="audience"><option value="public">Parents / public (link)</option><option value="staff">Staff (must sign in)</option></select>
        </label>
        <label>Start from
            <select name="template">
                <option value="">Blank form</option>
                <option value="consent">Parent consent (excursion / activity)</option>
                <option value="survey">Parent satisfaction survey</option>
                <option value="rsvp">Event RSVP</option>
            </select>
        </label>
        <div class="gate-actions"><button class="btn btn-primary" type="submit">Create form</button><button type="button" class="btn btn-ghost" data-help="new-form">Cancel</button></div>
    </form>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['form' => ['Form'], 'by' => ['Made by'], 'made' => ['Made on', 'date'], 'closes' => ['Closes', 'date'], 'for' => ['For'], 'status' => ['Status'], 'qs' => ['Questions', 'num', 'num'], 'resp' => ['Responses', 'num', 'num']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($forms as $f): [$sl, $st] = FormDef::STATUSES[$f['status']]; ?>
    <tr>
        <td data-c="form"><a href="<?= e(url('forms/' . $f['id'])) ?>"><strong><?= e($f['title']) ?></strong></a></td>
        <td data-c="by"><?= e($f['author'] ?? '—') ?></td>
        <td data-c="made" data-s="<?= e((string) $f['created_at']) ?>" style="white-space:nowrap"><?= e(fmt_date($f['created_at'])) ?></td>
        <td data-c="closes" data-s="<?= e((string) $f['closes_on']) ?>" style="white-space:nowrap"><?= $f['closes_on'] ? e(fmt_date($f['closes_on'])) : '' ?></td>
        <td data-c="for"><?= $f['audience'] === 'staff' ? 'Staff' : 'Parents / public' ?></td>
        <td data-c="status"><?= badge($sl, $st) ?></td>
        <td data-c="qs" class="num"><?= (int) $f['fields'] ?></td>
        <td data-c="resp" class="num"><a href="<?= e(url('forms/' . $f['id'] . '/responses')) ?>"><?= (int) $f['responses'] ?></a></td>
        <td class="xg-act"><a class="btn btn-sm" href="<?= e(url('forms/' . $f['id'])) ?>">Edit</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $forms ? ' hidden' : '' ?> data-xg-empty>No forms yet.</p>
</div>
</div>
