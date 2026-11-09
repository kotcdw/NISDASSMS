<?php
declare(strict_types=1);

/** Administration → School setup → List columns. */
class ListColumnsController extends Controller
{
    public function index(): void
    {
        $on = [];
        foreach (array_keys(ListColumns::LISTS) as $l) {
            $on[$l] = ListColumns::on($l);
        }
        $this->view('setup/columns', ['title' => 'List columns', 'on' => $on]);
    }

    public function save(): void
    {
        $posted = (array) ($_POST['cols'] ?? []);
        foreach (array_keys(ListColumns::LISTS) as $l) {
            ListColumns::save($l, array_map('strval', (array) ($posted[$l] ?? [])));
        }
        audit('list_columns_saved', 'settings', null, array_combine(array_keys(ListColumns::LISTS), array_map([ListColumns::class, 'on'], array_keys(ListColumns::LISTS))));
        flash('success', 'Columns saved. The lists now show only the ticked columns.');
        redirect('setup/columns');
    }
}
