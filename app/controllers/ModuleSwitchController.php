<?php
declare(strict_types=1);

/** Administration → School setup → Modules on/off: Administrators and Super Admins switch whole modules on or off. */
class ModuleSwitchController extends Controller
{
    public function index(): void
    {
        $this->view('setup/modules', ['title' => 'Modules on/off', 'groups' => ModuleSwitch::grouped(), 'off' => ModuleSwitch::off()]);
    }

    public function save(): void
    {
        if (!in_array(Auth::role(), ['super_admin', Permissions::ADMIN_ROLE], true)) { // belt and braces: the route already needs modules.switch
            http_response_code(403);
            $this->view('errors/403', ['title' => 'Access denied']);
            return;
        }
        $on = array_map('strval', (array) ($_POST['on'] ?? []));
        $off = array_values(array_diff(array_keys(ModuleSwitch::MODULES), $on));
        $before = ModuleSwitch::off();
        ModuleSwitch::save($off);
        $nowOff = array_values(array_diff($off, $before));
        $nowOn = array_values(array_diff($before, $off));
        if (!$nowOff && !$nowOn) {
            flash('info', 'Nothing changed.');
            redirect('setup/modules');
        }
        audit('modules_switched', 'settings', null, ['switched_off' => $nowOff, 'switched_on' => $nowOn]);
        unset($_SESSION['attention']);
        $names = fn(array $keys) => implode(', ', array_map([ModuleSwitch::class, 'label'], $keys));
        flash('success', trim(($nowOff ? 'Switched off: ' . $names($nowOff) . '. ' : '') . ($nowOn ? 'Switched on: ' . $names($nowOn) . '.' : '')));
        redirect('setup/modules');
    }
}
