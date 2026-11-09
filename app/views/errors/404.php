<?php
$u = auth();
$code = 404;
$tone = 'missing';
$heading = 'Page not found';
$lead = 'It may have been moved or removed.';
$points = [];
$actions = $u ? [[can('staff') ? 'Dashboard' : 'Home', url(can('staff') ? 'dashboard' : portal_home()), true]] : [['Sign in', url('login'), true]];
require __DIR__ . '/_page.php';
