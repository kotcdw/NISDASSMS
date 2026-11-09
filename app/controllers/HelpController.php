<?php
declare(strict_types=1);

/** Short how-to guide, showing the parts that matter for the signed-in person's role. */
class HelpController extends Controller
{
    public function index(): void
    {
        $all = can('staff') && !empty($_GET['all']);
        $this->view('help/index', ['title' => 'Help & training guide', 'all' => $all, 'role' => Auth::role()]);
    }
}
