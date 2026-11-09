<?php
/**
 * The app this page belongs to (for the label above the page title). Staff apps and their pages are listed in
 * AppMenu (the left panel); parents and students get one portal panel with only their own pages.
 * Sets $anArea (app name or null), $anActive (current portal page), $anTabs (portal pages), $anAreas.
 */
$anPath = current_route();
$anMatch = function (string $prefix) use ($anPath): int {
    return ($anPath === $prefix || str_starts_with($anPath, $prefix . '/')) ? strlen($prefix) : 0;
};
$anArea = can('staff') ? AppMenu::forRoute($anPath) : null;
$anActive = null;
$anAreas = [];
$anTabs = [];
// Parents and students: one portal panel with only their own pages (least privilege — no staff menus)
if (Auth::check() && !can('staff')) {
    $anArea = can('portal.parent') ? 'Parent portal' : 'My learning';
    $anTabs = [];
    $anActive = null;
    $anBest = 0;
    $seenR = [];
    foreach (Menu::visible() as $mi) {
        if (isset($seenR[$mi[0]])) {
            continue;
        }
        $seenR[$mi[0]] = true;
        $anTabs[] = [$mi[0], $mi[1], 'auth', [$mi[0]]];
        if (($n = $anMatch($mi[0])) > $anBest) {
            [$anBest, $anActive] = [$n, count($anTabs) - 1];
        }
    }
    $anAreas[$anArea] = $anTabs;
}
