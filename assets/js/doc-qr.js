/* QR codes on printed receipts and vouchers (uses qrcode.js, MIT, Kazuhiko Arase). */
(function () {
    if (typeof qrcode !== 'function') return;
    document.querySelectorAll('[data-doc-qr]').forEach(function (box) {
        var qr = qrcode(0, 'M');
        qr.addData(box.getAttribute('data-doc-qr'));
        qr.make();
        box.innerHTML = qr.createSvgTag({ cellSize: 3, margin: 0, scalable: true });
        var svg = box.querySelector('svg');
        if (svg) { svg.setAttribute('role', 'img'); svg.setAttribute('aria-label', 'QR code to check this document is genuine'); }
    });
})();
