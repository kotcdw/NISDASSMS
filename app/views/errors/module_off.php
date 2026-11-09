<?php
/* A page of a module the Super Admin has switched off (School setup → Modules on/off). Expects $module (label). */
$u = auth();
$code = 403;
$tone = 'lock';
$heading = 'This module is switched off';
$lead = ($module ?? 'This module') . ' is not in use at the moment. Its records are kept and come back when it is switched on again.';
$points = [];
$actions = [];
if ($u && Auth::role() === 'super_admin') {
    $actions[] = ['Modules on/off', url('setup/modules'), true];
}
$actions[] = $u ? [can('staff') ? 'Dashboard' : 'Home', url(can('staff') ? 'dashboard' : portal_home()), !$actions] : ['Sign in', url('login'), true];
require __DIR__ . '/_page.php';
