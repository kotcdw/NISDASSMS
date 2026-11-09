<?php
declare(strict_types=1);

/** NISDAS APPS (the app launcher) and the feature guide with a "learn more" page per feature. */
class ModuleController extends Controller
{
    /** NISDAS APPS: the picture tiles followed by every other app the person may open. */
    public function index(): void
    {
        if (!can('staff')) {
            redirect(portal_home()); // parents and students only see their own portal
        }
        $this->view('modules/engine', ['title' => 'NISDAS APPS', 'tiles' => Engine::tiles()]);
    }

    /** Feature guide: a card per feature with a "learn more" page. */
    public function guide(): void
    {
        if (!can('staff')) {
            redirect(portal_home());
        }
        $this->view('modules/index', ['title' => 'Feature guide', 'groups' => Modules::grouped()]);
    }

    public function show(string $slug): void
    {
        if (!can('staff')) {
            redirect(portal_home());
        }
        $all = Modules::all();
        $m = $all[$slug] ?? $this->notFound();
        $route = Modules::target($m);
        if ($route === null) {
            $this->notFound();
        }
        $this->view('modules/show', ['title' => $m[1], 'key' => $slug, 'm' => $m, 'route' => $route]);
    }
}
