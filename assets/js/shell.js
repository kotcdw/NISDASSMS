// NISDAS SMS — small page-shell helpers. (The old side-menu and drawer code was removed on 9 Nov 2026:
// the left app panel needs no JavaScript.)
(function () {
    'use strict';
    document.documentElement.classList.add('js');

    function ready(fn) { if (document.readyState !== 'loading') fn(); else document.addEventListener('DOMContentLoaded', fn); }

    ready(function () {
        // "More ▾" and other drop-down menus left empty (every link removed for this role): hide them
        // (for browsers without CSS :has(); the stylesheet does the same for the others)
        Array.prototype.forEach.call(document.querySelectorAll('details.menu-btn, details.xg-cols.xg-print'), function (d) {
            var box = d.querySelector(':scope > div');
            if (box && !box.querySelector('a, button, input, select, label')) d.hidden = true;
        });
    });
})();
