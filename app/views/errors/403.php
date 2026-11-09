<?php
$u = auth();
$code = 403;
$tone = 'lock';
if (!empty($blocked)) {
    $heading = 'This location is protected';
    $lead = 'Open it from the menu instead.';
    $points = [];
} elseif (!empty($readOnly)) {
    $heading = 'Read-only access';
    $lead = 'Nothing was changed.';
    $points = [];
} else {
    $heading = 'No access';
    $lead = $u ? 'Your role (' . Auth::roleLabel($u['role']) . ') cannot open this page.' : 'Please sign in to continue.';
    $points = [];
}
$actions = $u
    ? [[can('staff') ? 'Dashboard' : 'Home', url(can('staff') ? 'dashboard' : portal_home()), true]]
    : [['Sign in', url('login'), true]];
require __DIR__ . '/_page.php';
