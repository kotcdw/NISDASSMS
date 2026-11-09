/* Draws the QR code for 2-step sign-in set-up (uses qrcode.js, MIT, Kazuhiko Arase). No data leaves the page. */
(function () {
    var box = document.querySelector('[data-qr]');
    if (!box || typeof qrcode !== 'function') return;
    var qr = qrcode(0, 'M');
    qr.addData(box.getAttribute('data-qr'));
    qr.make();
    box.innerHTML = qr.createSvgTag({ cellSize: 5, margin: 2, scalable: true });
    var svg = box.querySelector('svg');
    if (svg) { svg.setAttribute('width', '220'); svg.setAttribute('height', '220'); svg.setAttribute('role', 'img'); svg.setAttribute('aria-label', box.getAttribute('data-qr-label') || 'QR code for your authenticator app'); }
})();
