// NISDAS SMS — small page-shell helpers. (The old side-menu and drawer code was removed on 9 Nov 2026:
// the left app panel needs no JavaScript.)
(function () {
    'use strict';
    var root = document.documentElement;
    root.classList.add('js');
    // Left app panel hidden on computers ("‹ Hide menu"): remembered on this device, applied before the page draws
    try { if (window.localStorage.getItem('nis.panel.hidden') === '1') root.classList.add('ap-hidden'); } catch (e) { /* private window */ }

    function ready(fn) { if (document.readyState !== 'loading') fn(); else document.addEventListener('DOMContentLoaded', fn); }

    ready(function () {
        // Reference numbers in lists (PV-2026-00004, INV-2026-00190…) stay on one line
        Array.prototype.forEach.call(document.querySelectorAll('td a, td strong'), function (a) {
            if (/^[A-Z]{2,6}-\d{2,4}-\d{2,6}$/.test(a.textContent.trim())) a.classList.add('nw');
        });
        Array.prototype.forEach.call(document.querySelectorAll('[data-app-panel-toggle]'), function (b) {
            b.addEventListener('click', function () {
                var hidden = root.classList.toggle('ap-hidden');
                try { window.localStorage.setItem('nis.panel.hidden', hidden ? '1' : '0'); } catch (e) { /* not saved */ }
            });
        });
        // "More ▾" and other drop-down menus left empty (every link removed for this role): hide them
        // (for browsers without CSS :has(); the stylesheet does the same for the others)
        Array.prototype.forEach.call(document.querySelectorAll('details.menu-btn, details.xg-cols.xg-print'), function (d) {
            var box = d.querySelector(':scope > div');
            if (box && !box.querySelector('a, button, input, select, label')) d.hidden = true;
        });
    });
})();
