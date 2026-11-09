// NISDAS SMS — small progressive enhancements (the app works without JS)
(function () {
    'use strict';

    // ---- Confirmation pop-up (replaces the browser's plain confirm box) --------------------------------
    // <form data-confirm="Question?"> or <button data-confirm-click="Question?">
    // Optional: data-confirm-title, data-confirm-detail, data-confirm-ok, data-confirm-tone="danger|warning|primary".
    var DANGER = /^(delete|remove|wipe|void|reverse|withdraw|reset|switch off|sign\b|undo|cancel|take|mark|close|reopen|re-open)/i;
    var OK_WORDS = { cancel: 'Yes, cancel', sign: 'Sign out', 'switch': 'Switch off', put: 'Restore', make: 'Make current', fill: 'Fill again', mark: 'Mark as lost',
        take: 'Take off the bus', go: 'Reset layout', set: 'Apply', start: 'Start now', 're-open': 'Re-open', reopen: 'Reopen', enrol: 'Enrol', pay: 'Pay out' };
    var ICONS = {
        danger: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 2.5 20h19L12 3z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M12 10v4.5M12 17.5v.01" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>',
        warning: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 7v6M12 16.5v.01" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>',
        primary: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M9.2 9.3a2.9 2.9 0 1 1 3.9 2.7c-.7.3-1.1.9-1.1 1.6v.6M12 17.2v.01" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>'
    };
    var dlg = null;
    function esc(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
    function buildDialog() {
        if (dlg) return dlg;
        dlg = document.createElement('dialog');
        dlg.className = 'cfm';
        dlg.setAttribute('aria-labelledby', 'cfm-title');
        dlg.setAttribute('aria-describedby', 'cfm-detail');
        dlg.innerHTML = '<form method="dialog" class="cfm-box"><div class="cfm-ico"></div><div class="cfm-main"><p class="cfm-eyebrow">Please confirm</p>' +
            '<h2 id="cfm-title"></h2><p id="cfm-detail" class="cfm-detail"></p></div>' +
            '<div class="cfm-actions"><button type="submit" value="no" class="btn cfm-no">Go back</button><button type="submit" value="yes" class="btn cfm-yes">Confirm</button></div></form>';
        document.body.appendChild(dlg);
        dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close('no'); }); // click outside = go back
        return dlg;
    }
    function ask(el, done) {
        var msg = el.getAttribute('data-confirm') || el.getAttribute('data-confirm-click') || 'Continue?';
        if (typeof HTMLDialogElement !== 'function') { if (window.confirm(msg)) done(); return; } // very old browsers
        var m = msg.match(/^(.*?\?)\s*(.*)$/);
        var title = el.getAttribute('data-confirm-title') || (m ? m[1] : msg);
        var detail = el.getAttribute('data-confirm-detail') || (m ? m[2] : '');
        var tone = el.getAttribute('data-confirm-tone') || (DANGER.test(msg) ? 'danger' : 'primary');
        var first = msg.trim().split(/\s+/)[0].replace(/[^A-Za-z-]/g, '').toLowerCase();
        var ok = el.getAttribute('data-confirm-ok') || OK_WORDS[first] || (first ? first.charAt(0).toUpperCase() + first.slice(1) : 'Confirm');
        var d = buildDialog();
        d.className = 'cfm tone-' + tone;
        d.querySelector('.cfm-ico').innerHTML = ICONS[tone] || ICONS.primary;
        d.querySelector('#cfm-title').innerHTML = esc(title);
        var det = d.querySelector('#cfm-detail');
        det.innerHTML = esc(detail);
        det.hidden = !detail;
        var yes = d.querySelector('.cfm-yes');
        yes.textContent = ok;
        yes.className = 'btn cfm-yes ' + (tone === 'danger' ? 'btn-danger' : 'btn-primary');
        d.returnValue = '';
        d.onclose = function () { d.onclose = null; if (d.returnValue === 'yes') done(); };
        d.showModal();
        (tone === 'danger' ? d.querySelector('.cfm-no') : yes).focus();
    }
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (!f.hasAttribute || !f.hasAttribute('data-confirm') || f._cfmOk) { if (f) f._cfmOk = false; return; }
        e.preventDefault();
        var sub = e.submitter || null;
        ask(f, function () {
            f._cfmOk = true;
            if (f.requestSubmit) { f.requestSubmit(sub && sub.form === f ? sub : undefined); }
            else { if (sub && sub.name) { var h = document.createElement('input'); h.type = 'hidden'; h.name = sub.name; h.value = sub.value; f.appendChild(h); } f.submit(); }
        });
    });
    document.addEventListener('click', function (e) {
        var b = e.target.closest ? e.target.closest('[data-confirm-click]') : null;
        if (!b || b._cfmOk) { if (b) b._cfmOk = false; return; }
        e.preventDefault();
        e.stopImmediatePropagation();
        ask(b, function () {
            b._cfmOk = true;
            if (b.form) { b.form._cfmOk = true; } // the button already asked: the form does not ask again
            b.click();
        });
    }, true);

    // "Go back" on error pages (falls back to the home page when there is no history)
    document.querySelectorAll('[data-back]').forEach(function (b) {
        b.addEventListener('click', function () { if (window.history.length > 1) { window.history.back(); } else { window.location.href = (document.querySelector('.err-actions a') || {}).href || '/'; } });
    });

    // Score sheet: Enter moves down to the next pupil like a spreadsheet
    document.querySelectorAll('.score-table input.score').forEach(function (inp) {
        inp.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') { return; }
            e.preventDefault();
            var cell = inp.closest('td'), row = inp.closest('tr'), idx = Array.prototype.indexOf.call(row.children, cell);
            var next = row.nextElementSibling;
            var target = next && next.children[idx] ? next.children[idx].querySelector('input') : null;
            if (target) { target.focus(); target.select(); }
        });
    });

    document.querySelectorAll('[data-print]').forEach(function (b) {
        b.addEventListener('click', function () { window.print(); });
    });

    // SMS character / part counter: <textarea data-sms-counter>
    document.querySelectorAll('[data-sms-counter]').forEach(function (ta) {
        var out = document.createElement('div');
        out.className = 'hint';
        out.style.marginTop = '.3rem';
        ta.insertAdjacentElement('afterend', out);
        var upd = function () {
            var n = ta.value.length, parts = n <= 160 ? 1 : Math.ceil(n / 153);
            out.textContent = n + ' characters · ' + parts + ' SMS' + (parts > 1 ? ' (each message costs ' + parts + ' credits)' : '');
        };
        ta.addEventListener('input', upd);
        upd();
    });

    // Attendance register: mark everyone with one status
    document.querySelectorAll('[data-mark-all]').forEach(function (b) {
        b.addEventListener('click', function () {
            var v = b.getAttribute('data-mark-all');
            b.form.querySelectorAll('input[type=radio][value="' + v + '"]').forEach(function (r) { if (!r.closest('tr[data-on-leave]')) r.checked = true; });
        });
    });

    // "Select all" checkbox: <input type="checkbox" data-check-all="classes[]">
    document.querySelectorAll('[data-check-all]').forEach(function (box) {
        box.addEventListener('change', function () {
            document.querySelectorAll('input[name="' + box.getAttribute('data-check-all') + '"]').forEach(function (c) { c.checked = box.checked; });
        });
    });

    // Auto-submit filters: <select data-autosubmit>
    document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
        el.addEventListener('change', function () { (el.form || el).submit(); }); // on a <select> or on a whole <form>
    });

    // Show/hide level selector when calendar audience = level
    var audience = document.querySelector('select[name="audience"]');
    var levelWrap = document.getElementById('level-wrap');
    if (audience && levelWrap) {
        var sync = function () { levelWrap.style.display = audience.value === 'level' ? '' : 'none'; };
        audience.addEventListener('change', sync);
        sync();
    }

    // Keep end date >= start date on date pairs
    var start = document.querySelector('input[name="start_date"]');
    var end = document.querySelector('input[name="end_date"]');
    if (start && end) {
        start.addEventListener('change', function () {
            end.min = start.value;
            if (!end.value || end.value < start.value) { end.value = start.value; }
        });
    }

    // Warn when an event falls on Sabbath (Saturday)
    var sabbathNote = document.getElementById('sabbath-note');
    if (sabbathNote && start) {
        var check = function () {
            var s = start.value, en = (end && end.value) || s, hit = false;
            if (s) {
                var d = new Date(s + 'T12:00:00'), last = new Date(en + 'T12:00:00');
                for (var i = 0; d <= last && i < 400; i++) {
                    if (d.getDay() === 6) { hit = true; break; }
                    d.setDate(d.getDate() + 1);
                }
            }
            sabbathNote.style.display = hit ? '' : 'none';
        };
        start.addEventListener('change', check);
        if (end) { end.addEventListener('change', check); }
        check();
    }

    // Requisition items: line totals and grand total
    var reqTable = document.querySelector('[data-req-items]');
    if (reqTable) {
        var fmt = function (n) { return n.toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
        var recalc = function () {
            var total = 0;
            reqTable.querySelectorAll('tbody tr').forEach(function (tr) {
                var q = parseFloat((tr.querySelector('[name="item_qty[]"]') || {}).value || '0') || 0;
                var c = parseFloat(((tr.querySelector('[name="item_cost[]"]') || {}).value || '0').replace(/,/g, '')) || 0;
                var cell = tr.querySelector('[data-line-total]');
                if (cell) { cell.textContent = q * c ? fmt(q * c) : ''; }
                total += q * c;
            });
            var t = document.querySelector('[data-req-total]');
            if (t) { t.textContent = fmt(total); }
        };
        reqTable.addEventListener('input', recalc);
        recalc();
    }

    // Quiz question form: show only the fields for the chosen type
    document.querySelectorAll('[data-qtype-form]').forEach(function (f) {
        var sel = f.querySelector('[data-qtype]');
        var sync = function () {
            f.querySelectorAll('[data-for]').forEach(function (d) { d.style.display = d.getAttribute('data-for') === sel.value ? '' : 'none'; });
        };
        sel.addEventListener('change', sync);
        sync();
    });

    // Quiz: countdown, autosave every 20 s, submit automatically when time is up
    var quizForm = document.getElementById('quiz-form');
    if (quizForm) {
        var saveUrl = quizForm.getAttribute('data-autosave');
        var status = quizForm.querySelector('[data-autosave-status]');
        var dirty = false, sending = false, done = false;
        quizForm.addEventListener('input', function () { dirty = true; });
        quizForm.addEventListener('change', function () { dirty = true; });
        quizForm.addEventListener('submit', function () { done = true; });
        var save = function () {
            if (!dirty || sending || done || !window.fetch) { return; }
            sending = true; dirty = false;
            fetch(saveUrl, { method: 'POST', body: new FormData(quizForm), credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
                .then(function (r) { return r.json(); })
                .then(function (j) { if (status) { status.textContent = j.ok ? 'Answers saved ' + new Date().toLocaleTimeString() : 'Time is up.'; } })
                .catch(function () { dirty = true; if (status) { status.textContent = 'Not saved — check your connection.'; } })
                .then(function () { sending = false; });
        };
        setInterval(save, 20000);
        var timer = document.querySelector('[data-quiz-deadline]');
        if (timer) {
            var offset = Date.now() - parseInt(timer.getAttribute('data-now'), 10);
            var deadline = parseInt(timer.getAttribute('data-quiz-deadline'), 10);
            var span = timer.querySelector('span');
            var tick = function () {
                var left = Math.max(0, Math.round((deadline - (Date.now() - offset)) / 1000));
                var m = Math.floor(left / 60), s = left % 60;
                span.textContent = m + ':' + (s < 10 ? '0' : '') + s;
                if (left <= 60) { timer.classList.add('low'); }
                if (left === 0 && !done) { done = true; timer.textContent = 'Time is up — submitting…'; quizForm.submit(); }
            };
            tick();
            setInterval(tick, 1000);
        }
    }
})();

// "+ Add new …" in dropdowns: <select data-quick-add="kind" data-quick-add-label="class" [data-quick-add-preset='{"field":"value"}']>
(function () {
    var selects = document.querySelectorAll('select[data-quick-add]');
    var urlMeta = document.querySelector('meta[name="quick-add-url"]');
    var tokenMeta = document.querySelector('meta[name="csrf-token"]');
    if (!selects.length || !urlMeta || !tokenMeta || typeof window.fetch !== 'function') { return; }
    var NEW = '__add_new__';

    selects.forEach(function (sel) {
        if (!document.getElementById('qa-tpl-' + sel.getAttribute('data-quick-add'))) { return; }
        var opt = document.createElement('option');
        opt.value = NEW;
        opt.textContent = '+ Add new ' + (sel.getAttribute('data-quick-add-label') || 'item') + '…';
        opt.className = 'qa-option';
        sel.appendChild(opt);
        sel.setAttribute('data-qa-prev', sel.value === NEW ? '' : sel.value);
        sel.addEventListener('focus', function () { if (sel.value !== NEW) { sel.setAttribute('data-qa-prev', sel.value); } });
        sel.addEventListener('change', function () {
            if (sel.value !== NEW) { sel.setAttribute('data-qa-prev', sel.value); return; }
            openDialog(sel);
        });
    });

    function openDialog(sel) {
        var kind = sel.getAttribute('data-quick-add');
        var label = sel.getAttribute('data-quick-add-label') || 'item';
        var dlg = document.createElement('dialog');
        dlg.className = 'qa-dialog';
        var form = document.createElement('form');
        form.className = 'form';
        form.noValidate = false;
        var h = document.createElement('h2');
        h.textContent = 'Add a new ' + label;
        form.appendChild(h);
        var body = document.createElement('div');
        body.appendChild(document.getElementById('qa-tpl-' + kind).content.cloneNode(true));
        form.appendChild(body);
        var preset = {};
        try { preset = JSON.parse(sel.getAttribute('data-quick-add-preset') || '{}'); } catch (e) { preset = {}; }
        Object.keys(preset).forEach(function (k) {
            var f = form.querySelector('[name="' + k + '"]');
            if (f) { f.value = preset[k]; }
        });
        var msg = document.createElement('div');
        msg.className = 'qa-msg';
        msg.setAttribute('role', 'status');
        form.appendChild(msg);
        var bar = document.createElement('div');
        bar.className = 'qa-actions';
        var cancel = document.createElement('button');
        cancel.type = 'button'; cancel.className = 'btn'; cancel.textContent = 'Cancel';
        var save = document.createElement('button');
        save.type = 'submit'; save.className = 'btn btn-primary'; save.textContent = 'Save and choose it';
        bar.appendChild(cancel); bar.appendChild(save);
        form.appendChild(bar);
        dlg.appendChild(form);
        document.body.appendChild(dlg);

        var finished = false;
        function close(restore) {
            if (restore) { sel.value = sel.getAttribute('data-qa-prev') || ''; if (sel.value === NEW) { sel.selectedIndex = 0; } }
            dlg.close();
            dlg.remove();
        }
        cancel.addEventListener('click', function () { close(!finished); });
        dlg.addEventListener('cancel', function (e) { e.preventDefault(); close(!finished); });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (finished) { close(false); return; }
            msg.className = 'qa-msg'; msg.textContent = 'Saving…';
            save.disabled = true;
            var data = new FormData(form);
            data.append('kind', kind);
            data.append('_token', tokenMeta.getAttribute('content'));
            fetch(urlMeta.getAttribute('content'), { method: 'POST', body: data, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    save.disabled = false;
                    if (!res.ok) { msg.className = 'qa-msg qa-error'; msg.textContent = res.error || 'Could not save.'; return; }
                    // Add the new item to every dropdown of this kind on the page, and choose it here
                    document.querySelectorAll('select[data-quick-add="' + kind + '"]').forEach(function (s) {
                        var o = document.createElement('option');
                        o.value = res.id; o.textContent = res.label;
                        var addOpt = s.querySelector('option[value="' + NEW + '"]');
                        s.insertBefore(o, addOpt);
                    });
                    sel.value = String(res.id);
                    sel.setAttribute('data-qa-prev', sel.value);
                    sel.dispatchEvent(new Event('change', { bubbles: true }));
                    finished = true;
                    body.style.display = 'none';
                    msg.className = 'qa-msg qa-ok';
                    msg.textContent = res.note || 'Added.';
                    cancel.style.display = 'none';
                    save.textContent = 'Done';
                    save.focus();
                })
                .catch(function () { save.disabled = false; msg.className = 'qa-msg qa-error'; msg.textContent = 'Could not reach the server. Check the connection and try again.'; });
        });
        dlg.showModal();
        var first = form.querySelector('input, select');
        if (first) { first.focus(); }
    }
})();

// Seating plans: drag a pupil onto a seat (swap if taken), or tap a pupil then tap a seat (phones, keyboard)
(function () {
    'use strict';
    document.querySelectorAll('[data-seating]').forEach(function (form) {
        var pool = form.querySelector('[data-pool]');
        var dirty = form.querySelector('.seat-dirty');
        var dragged = null, picked = null;
        var sync = function () {
            form.querySelectorAll('.seat').forEach(function (s) {
                var p = s.querySelector('.seat-pupil');
                s.querySelector('input[type=hidden]').value = p ? p.getAttribute('data-pid') : '';
            });
            var empty = pool.querySelector('.seat-pool-empty');
            if (empty) { empty.hidden = !!pool.querySelector('.seat-pupil'); }
            if (dirty) { dirty.hidden = false; }
        };
        var place = function (pupil, target) {
            if (!pupil || !target || pupil.parentElement === target) { return; }
            var from = pupil.parentElement;
            if (target.classList.contains('seat')) {
                var other = target.querySelector('.seat-pupil');
                if (other && other !== pupil) { from.appendChild(other); }
            }
            target.appendChild(pupil);
            sync();
        };
        var unpick = function () { if (picked) { picked.classList.remove('picked'); picked = null; } };
        form.addEventListener('dragstart', function (e) {
            var p = e.target.closest ? e.target.closest('.seat-pupil') : null;
            if (!p) { return; }
            dragged = p;
            p.classList.add('dragging');
            try { e.dataTransfer.setData('text/plain', p.getAttribute('data-pid')); e.dataTransfer.effectAllowed = 'move'; } catch (err) { /* old browsers */ }
        });
        form.addEventListener('dragend', function () { if (dragged) { dragged.classList.remove('dragging'); } dragged = null; });
        form.addEventListener('dragover', function (e) {
            if (dragged && e.target.closest && e.target.closest('.seat, [data-pool]')) { e.preventDefault(); }
        });
        form.addEventListener('drop', function (e) {
            var t = e.target.closest ? e.target.closest('.seat, [data-pool]') : null;
            if (!t || !dragged) { return; }
            e.preventDefault();
            place(dragged, t);
        });
        form.addEventListener('click', function (e) {
            var p = e.target.closest ? e.target.closest('.seat-pupil') : null;
            var t = e.target.closest ? e.target.closest('.seat, [data-pool]') : null;
            if (picked) {
                if (p === picked) { unpick(); return; }
                place(picked, t);
                unpick();
                return;
            }
            if (p) { picked = p; p.classList.add('picked'); }
        });
    });
})();

// ---- Installable app: service worker and phone notifications (Sprint 19) ----
(function () {
    var swMeta = document.querySelector('meta[name="sw-url"]');
    if (!swMeta || !('serviceWorker' in navigator)) return;
    var base = swMeta.content.replace(/sw\.js$/, '');
    navigator.serviceWorker.register(swMeta.content, { scope: base }).catch(function () {});
    var box = document.querySelector('[data-push-box]');
    if (!box) return;
    var status = box.querySelector('[data-push-status]'), on = box.querySelector('[data-push-on]'), off = box.querySelector('[data-push-off]');
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    function say(t) { if (status) status.textContent = t; }
    function b64(s) { s = s.replace(/-/g, '+').replace(/_/g, '/'); while (s.length % 4) s += '='; var r = atob(s), a = new Uint8Array(r.length); for (var i = 0; i < r.length; i++) a[i] = r.charCodeAt(i); return a; }
    function post(u, body) { return fetch(base + u, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify(body) }); }
    if (!('PushManager' in window) || !window.isSecureContext) { say('Phone notifications need the installed app on the live https address.'); if (on) on.disabled = true; return; }
    navigator.serviceWorker.ready.then(function (reg) {
        reg.pushManager.getSubscription().then(function (sub) {
            say(sub ? 'Phone notifications are ON for this device.' : (Notification.permission === 'denied' ? 'Notifications are blocked in this browser\'s settings.' : 'Phone notifications are off for this device.'));
            if (on) on.hidden = !!sub; if (off) off.hidden = !sub;
        });
        if (on) on.addEventListener('click', function () {
            fetch(base + 'push/key', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (k) {
                if (!k.key) { say('Not available yet — ask the administrator to update the database.'); return; }
                return Notification.requestPermission().then(function (p) {
                    if (p !== 'granted') { say('Permission not given.'); return; }
                    return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64(k.key) }).then(function (sub) {
                        return post('push/subscribe', sub.toJSON()).then(function () { say('Phone notifications are ON for this device.'); on.hidden = true; if (off) off.hidden = false; });
                    });
                });
            }).catch(function () { say('Could not turn on notifications on this device.'); });
        });
        if (off) off.addEventListener('click', function () {
            reg.pushManager.getSubscription().then(function (sub) { if (!sub) return; return post('push/unsubscribe', { endpoint: sub.endpoint }).then(function () { return sub.unsubscribe(); }); })
                .then(function () { say('Phone notifications are off for this device.'); off.hidden = true; if (on) on.hidden = false; });
        });
    });
})();

// Analytic Dashboard: live figures. Every card is rebuilt on the server and swapped in place every minute while the
// page is visible (paused when the tab is hidden, refreshed at once on return). A failed refresh keeps the old figures,
// says when they were last updated and tries again.
(function () {
    var root = document.querySelector('[data-live]');
    if (!root || !window.fetch) return;
    var url = root.getAttribute('data-live');
    var secs = parseInt(root.getAttribute('data-live-secs'), 10) || 60;
    var marker = root.querySelector('[data-live-at]');
    var timer = null, busy = false, lastOk = Date.now();
    function hhmm(d) { return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2); }
    function mark(text, failed) {
        if (!marker) return;
        marker.lastChild.textContent = text;
        marker.classList.toggle('is-stale', !!failed);
    }
    function refresh() {
        if (busy || document.hidden) return;
        if (document.querySelector('details[open].profile-menu, details[open].attn-menu')) return schedule();
        busy = true;
        fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' } })
            .then(function (r) { if (!r.ok || (r.headers.get('content-type') || '').indexOf('json') < 0) throw new Error('bad'); return r.json(); })
            .then(function (j) {
                Object.keys(j.cards || {}).forEach(function (k) {
                    var el = root.querySelector('[data-card="' + k + '"]');
                    if (el && el.outerHTML !== j.cards[k]) el.outerHTML = j.cards[k];
                });
                root.querySelectorAll('[data-card]').forEach(function (el) { if (!(el.getAttribute('data-card') in (j.cards || {}))) el.remove(); });
                lastOk = Date.now();
                mark('Live · updated ' + (j.asOf || hhmm(new Date())), false);
            })
            .catch(function () { mark('Last updated ' + hhmm(new Date(lastOk)) + ' · retrying', true); })
            .then(function () { busy = false; schedule(); });
    }
    function schedule() { clearTimeout(timer); timer = setTimeout(refresh, secs * 1000); }
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) { clearTimeout(timer); } else if (Date.now() - lastOk > 15000) { refresh(); } else { schedule(); }
    });
    schedule();
})();

// Phone app settings: live preview of the name under the icon and the top-bar colour
(function () {
    var phone = document.querySelector('[data-app-preview]');
    if (!phone) return;
    var name = phone.querySelector('[data-app-name]');
    var input = document.querySelector('[data-app-name-input]');
    if (input && name) input.addEventListener('input', function () { name.textContent = input.value.trim() || 'NISDAS'; });
    document.querySelectorAll('[data-app-color]').forEach(function (r) {
        r.addEventListener('change', function () { phone.style.setProperty('--app-color', r.value); });
    });
})();

// Page-by-page lists: long tables show 25 rows at a time with ‹ 1 2 3 › and a rows-per-page choice (remembered in
// this browser). Filtering, search and sorting work on the whole list; the pager then shows one page of what matches.
var NisPager = (function () {
    var KEY = 'nisdas.pager.per';
    var SIZES = [25, 50, 100, 0];
    function perPage() { var v = 25; try { v = parseInt(localStorage.getItem(KEY) || '25', 10); } catch (e) { v = 25; } return SIZES.indexOf(v) === -1 ? 25 : v; }
    var all = [];
    function make(anchor, getRows) {
        // the list is already paged by the server (‹ 1 2 3 › links): leave it as it is
        if (document.querySelector('main nav.pager:not(.xg-pager)')) return { draw: function () {}, reset: function () {} };
        var nav = document.createElement('nav');
        nav.className = 'pager xg-pager';
        nav.setAttribute('aria-label', 'Pages');
        anchor.parentNode.insertBefore(nav, anchor.nextSibling);
        var page = 1, last = [];
        function btn(label, p, active, disabled) {
            var b = document.createElement('button');
            b.type = 'button'; b.textContent = label;
            if (active) { b.className = 'active'; b.setAttribute('aria-current', 'page'); }
            if (disabled) b.disabled = true;
            else b.addEventListener('click', function () { page = p; draw(); var top = anchor.getBoundingClientRect().top; if (top < 0) anchor.scrollIntoView({ block: 'start' }); });
            return b;
        }
        function draw() {
            var rows = getRows(), per = perPage(), n = rows.length;
            var pages = per ? Math.max(1, Math.ceil(n / per)) : 1;
            if (page > pages) page = pages;
            last.forEach(function (r) { r.classList.remove('pg-off'); });
            rows.forEach(function (r, i) { if (per && (i < (page - 1) * per || i >= page * per)) r.classList.add('pg-off'); else r.classList.remove('pg-off'); });
            last = rows;
            nav.innerHTML = '';
            if (n <= 25) { nav.hidden = true; return; }
            nav.hidden = false;
            var from = per ? (page - 1) * per + 1 : 1, to = per ? Math.min(n, page * per) : n;
            var info = document.createElement('span'); info.className = 'pg-info'; info.textContent = 'Showing ' + from + '–' + to + ' of ' + n;
            nav.appendChild(info);
            var links = document.createElement('span'); links.className = 'pg-links';
            if (pages > 1) {
                links.appendChild(btn('‹ Prev', page - 1, false, page === 1));
                var prev = 0;
                for (var i = 1; i <= pages; i++) {
                    if (i !== 1 && i !== pages && Math.abs(i - page) > 2) continue;
                    if (prev && i - prev > 1) { var g = document.createElement('span'); g.className = 'pager-gap'; g.textContent = '…'; links.appendChild(g); }
                    links.appendChild(btn(String(i), i, i === page, false));
                    prev = i;
                }
                links.appendChild(btn('Next ›', page + 1, false, page === pages));
            }
            nav.appendChild(links);
            var lab = document.createElement('label'); lab.className = 'pg-size'; lab.textContent = 'Rows ';
            var sel = document.createElement('select');
            SIZES.forEach(function (v) { var o = document.createElement('option'); o.value = v; o.textContent = v ? v : 'All'; if (v === per) o.selected = true; sel.appendChild(o); });
            sel.addEventListener('change', function () { try { localStorage.setItem(KEY, sel.value); } catch (e) { /* not remembered */ } page = 1; all.forEach(function (p) { p.draw(); }); });
            lab.appendChild(sel);
            nav.appendChild(lab);
        }
        var api = { draw: draw, reset: function () { page = 1; draw(); } };
        all.push(api);
        draw();
        return api;
    }
    return { make: make };
})();

// Spreadsheet-style lists: <div data-xgrid="key"> — sort by column, filter in each column, choose columns (remembered
// in this browser), and export exactly what is on screen to Excel (CSV with BOM; formulas defused).
(function () {
    document.querySelectorAll('[data-xgrid]').forEach(function (box) {
        var key = 'nisdas.xgrid.' + box.getAttribute('data-xgrid');
        var table = box.querySelector('table.xgrid');
        var tbody = table.tBodies[0];
        var rows = Array.prototype.slice.call(tbody.rows);
        var count = box.querySelector('[data-xg-count]');
        var empty = box.querySelector('[data-xg-empty]');
        var noun = (count && (count.getAttribute('data-xg-noun') || count.textContent.replace(/^[\d,]+\s*/, ''))) || 'rows';
        function cell(tr, c) { return tr.querySelector('td[data-c="' + c + '"]'); }
        function val(td) { return td ? (td.hasAttribute('data-s') ? td.getAttribute('data-s') : td.textContent.trim()) : ''; }
        function shown() { return rows.filter(function (r) { return !r.hidden; }); }
        function renumber() {
            var n = 0;
            rows.forEach(function (r) { if (!r.hidden) { n++; var c = r.querySelector('.xg-n'); if (c) c.textContent = n; } });
            if (count) count.textContent = (n === rows.length ? n : n + ' of ' + rows.length) + ' ' + (rows.length === 1 && n === 1 ? noun.replace(/s$/, '') : noun);
            if (empty) empty.hidden = rows.length > 0 && n > 0;
            if (pg) pg.reset();
        }
        var pg = null;
        // one search box: every word must appear somewhere in the row
        // plus drop-downs <select data-xg-pick="column">: the cell must contain the chosen value
        var search = box.querySelector('[data-xg-search]');
        var picks = Array.prototype.slice.call(box.querySelectorAll('select[data-xg-pick]'));
        if (search || picks.length) {
            var run = function () {
                var words = search ? search.value.toLowerCase().trim().split(/\s+/).filter(Boolean) : [];
                rows.forEach(function (r) {
                    var t = r.textContent.toLowerCase();
                    var ok = words.every(function (w) { return t.indexOf(w) !== -1; });
                    picks.forEach(function (p) {
                        if (ok && p.value !== '') { var td = cell(r, p.getAttribute('data-xg-pick')); ok = !!td && td.textContent.toLowerCase().indexOf(p.value.toLowerCase()) !== -1; }
                    });
                    r.hidden = !ok;
                });
                renumber();
            };
            if (search) search.addEventListener('input', run);
            picks.forEach(function (p) { p.addEventListener('change', run); });
            if ((search && search.value) || picks.some(function (p) { return p.value !== ''; })) run();
        }
        // --- sorting
        var sortCol = null, dir = 1;
        table.querySelectorAll('thead th[data-c] .xg-sort').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var th = btn.closest('th'), c = th.getAttribute('data-c'), type = th.getAttribute('data-type');
                dir = sortCol === c ? -dir : 1;
                sortCol = c;
                table.querySelectorAll('thead th[data-c]').forEach(function (h) { h.removeAttribute('aria-sort'); });
                th.setAttribute('aria-sort', dir === 1 ? 'ascending' : 'descending');
                rows.sort(function (a, b) {
                    var x = val(cell(a, c)), y = val(cell(b, c));
                    if (x === '' && y !== '') return 1;
                    if (y === '' && x !== '') return -1;
                    if (type === 'num') return (parseFloat(x) - parseFloat(y)) * dir;
                    return x.localeCompare(y, undefined, { numeric: true, sensitivity: 'base' }) * dir;
                });
                rows.forEach(function (r) { tbody.appendChild(r); });
                renumber();
            });
        });
        // --- choose columns (remembered per browser)
        var boxes = Array.prototype.slice.call(box.querySelectorAll('[data-xg-col]'));
        function showCol(c, on) { table.querySelectorAll('[data-c="' + c + '"]').forEach(function (el) { el.hidden = !on; }); }
        var saved = null;
        try { saved = JSON.parse(localStorage.getItem(key) || 'null'); } catch (e) { saved = null; }
        boxes.forEach(function (b) {
            var c = b.getAttribute('data-xg-col');
            if (saved && typeof saved[c] === 'boolean') b.checked = saved[c];
            showCol(c, b.checked);
            b.addEventListener('change', function () {
                showCol(c, b.checked);
                var st = {};
                boxes.forEach(function (x) { st[x.getAttribute('data-xg-col')] = x.checked; });
                try { localStorage.setItem(key, JSON.stringify(st)); } catch (e) { /* private mode: not remembered */ }
            });
        });
        // --- export what is on screen: CSV made here; Excel, Word and PDF made by the server from the same rows
        // Export menu header: how much will be exported
        // controls inside the box, or elsewhere on the page marked data-xg-for="<this list>"
        function ctl(sel) {
            var out = Array.prototype.slice.call(box.querySelectorAll(sel));
            document.querySelectorAll('[data-xg-for="' + box.getAttribute('data-xgrid') + '"]').forEach(function (w) {
                out = out.concat(w.matches(sel) ? [w] : Array.prototype.slice.call(w.querySelectorAll(sel)));
            });
            return out;
        }
        ctl('details.export-menu').forEach(function (m) {
            m.addEventListener('toggle', function () {
                var meta = m.querySelector('[data-export-meta]');
                if (!m.open || !meta) return;
                var n = shown().length, c = table.querySelectorAll('thead tr:first-child th[data-c]:not([hidden])').length;
                meta.textContent = n + (n === 1 ? ' row' : ' rows') + ' \u00b7 ' + c + (c === 1 ? ' column' : ' columns');
            });
        });
        ctl('[data-xg-export]').forEach(function (exp) {
            exp.addEventListener('click', function () {
                var format = exp.getAttribute('data-xg-export') || 'csv';
                var cols = Array.prototype.slice.call(table.querySelectorAll('thead tr:first-child th[data-c]')).filter(function (h) { return !h.hidden; });
                var txt = function (el) { return el ? String(el.textContent).replace(/\s+/g, ' ').trim() : ''; };
                var head = cols.map(function (h) { return txt(h); });
                var body = shown().map(function (r) { return cols.map(function (h) { return txt(cell(r, h.getAttribute('data-c'))); }); });
                var file = (box.getAttribute('data-xgrid-file') || 'list') + '-' + new Date().toISOString().slice(0, 10);
                var menu = exp.closest('details'); if (menu) menu.open = false;
                if (format === 'csv') {
                    var q = function (s) { if (/^[=+\-@\t\r]/.test(s)) s = "'" + s; return '"' + s.replace(/"/g, '""') + '"'; };
                    var lines = [head.map(q).join(',')].concat(body.map(function (r) { return r.map(q).join(','); }));
                    var blob = new Blob(['\ufeff' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
                    var a = document.createElement('a');
                    a.href = URL.createObjectURL(blob);
                    a.download = file + '.csv';
                    document.body.appendChild(a);
                    a.click();
                    setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
                    return;
                }
                var urlMeta = document.querySelector('meta[name="export-url"]'), tok = document.querySelector('meta[name="csrf-token"]');
                if (!urlMeta || !tok) return;
                var h1 = document.querySelector('.page-head h1');
                var f = document.createElement('form');
                f.method = 'post';
                f.action = urlMeta.getAttribute('content');
                f.hidden = true;
                [['_token', tok.getAttribute('content')], ['format', format], ['file', file], ['title', h1 ? txt(h1) : document.title],
                 ['data', JSON.stringify({ head: head, rows: body })]].forEach(function (kv) {
                    var i = document.createElement('input'); i.type = 'hidden'; i.name = kv[0]; i.value = kv[1]; f.appendChild(i);
                });
                document.body.appendChild(f);
                f.submit();
                setTimeout(function () { f.remove(); }, 2000);
            });
        });
        pg = NisPager.make(table.closest('.table-wrap') || table, shown);
        renumber();
    });
    // other long tables on working pages (not registers / mark sheets with boxes to fill in, nor print-outs)
    document.querySelectorAll('main table').forEach(function (t) {
        if (t.closest('[data-xgrid]') || t.hasAttribute('data-nopage') || t.closest('[data-nopage]') || !t.tBodies[0]) return;
        if (/\b(tt-grid|att-grid|att-table|score-table|rc-scores|rc-info|lines|cash-table)\b/.test(t.className)) return;
        var body = t.tBodies[0];
        if (body.rows.length <= 25 || body.querySelector('input:not([type=hidden]), select, textarea')) return;
        var rows = Array.prototype.slice.call(body.rows);
        NisPager.make(t.closest('.table-wrap') || t, function () { return rows.filter(function (r) { return !r.hidden && r.parentNode === body; }); });
    });
})();

// Drop-down menus (row ⋯ menus and header "Tools ▾"): one open at a time, close on outside click / Esc / scroll.
// Row menus float above scrolling tables (position: fixed) so they are never cut off.
(function () {
    var menus = function () { return Array.prototype.slice.call(document.querySelectorAll('details.rowmenu[open], details.menu-btn[open], details.profile-menu[open], details.app-module-group[open]')); };
    var closeAll = function (except) { menus().forEach(function (d) { if (d !== except) d.removeAttribute('open'); }); };
    var place = function (d) {
        var box = d.querySelector(':scope > div'), r = d.querySelector('summary').getBoundingClientRect();
        if (r.bottom < 0 || r.top > window.innerHeight) { d.removeAttribute('open'); return; }
        box.style.position = 'fixed';
        if (d.classList.contains('fin-mi') || d.classList.contains('card-more') || d.classList.contains('app-module-group')) { // Keep module menus inside the viewport
            box.style.right = 'auto';
            box.style.left = Math.max(8, Math.min(r.left, window.innerWidth - box.offsetWidth - 8)) + 'px';
        } else {
            box.style.right = Math.max(8, window.innerWidth - r.right) + 'px';
        }
        box.style.top = (r.bottom + 4) + 'px';
        var h = box.offsetHeight;
        if (r.bottom + 4 + h > window.innerHeight - 8) box.style.top = Math.max(8, r.top - h - 4) + 'px';
    };
    document.addEventListener('toggle', function (ev) {
        var d = ev.target;
        if (!d.matches || !d.matches('details.rowmenu, details.menu-btn, details.profile-menu, details.app-module-group') || !d.open) return;
        closeAll(d);
        if (d.classList.contains('rowmenu') || d.classList.contains('tab-more') || d.classList.contains('card-more') || d.classList.contains('fin-mi') || d.classList.contains('app-module-group')) place(d); // tab rows scroll sideways, so their menus float
    }, true);
    document.addEventListener('click', function (ev) { if (!ev.target.closest('details.rowmenu, details.menu-btn, details.profile-menu, details.app-module-group')) closeAll(null); });
    document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') closeAll(null); });
    // keep an open row menu attached to its row while the page or table scrolls
    window.addEventListener('scroll', function () { menus().forEach(function (d) { if (d.classList.contains('rowmenu') || d.classList.contains('tab-more') || d.classList.contains('card-more') || d.classList.contains('fin-mi') || d.classList.contains('app-module-group')) place(d); }); }, true);
})();

// Help links: <button data-help="id"> shows/hides <div id="id" class="help-box" hidden>
document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-help]');
    if (!b) return;
    var box = document.getElementById(b.getAttribute('data-help'));
    if (!box) return;
    if (b.tagName === 'A') ev.preventDefault();
    var menu = b.closest('details[open]');
    if (menu) menu.open = false;
    box.hidden = !box.hidden;
    b.setAttribute('aria-expanded', box.hidden ? 'false' : 'true');
    if (!box.hidden) box.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
});

// Linked drop-downs: <select data-limits="#target"> shows only the target's options whose data-group matches (empty = all)
document.querySelectorAll('select[data-limits]').forEach(function (src) {
    Array.prototype.forEach.call(document.querySelectorAll(src.getAttribute('data-limits')), function (target) { linkSelect(src, target); });
});
function linkSelect(src, target) {
    var apply = function () {
        var g = src.value;
        Array.prototype.forEach.call(target.querySelectorAll('option[data-group]'), function (o) { o.hidden = g !== '' && o.getAttribute('data-group') !== g; o.disabled = o.hidden; });
        Array.prototype.forEach.call(target.querySelectorAll('optgroup[data-group]'), function (og) { og.hidden = g !== '' && og.getAttribute('data-group') !== g; });
        var cur = target.selectedOptions[0];
        if (cur && cur.hidden) target.value = '';
    };
    src.addEventListener('change', apply);
    apply();
}

// Family payment: share the amount received over the open bills (oldest first) and show what is kept as credit
document.querySelectorAll('form[data-family-pay]').forEach(function (f) {
    var amt = f.querySelector('[data-fp-amount]');
    var ins = Array.prototype.slice.call(f.querySelectorAll('input[data-fp-bal]'));
    var outA = f.querySelector('[data-fp-applied]'), outC = f.querySelector('[data-fp-credit]');
    var touched = false;
    var num = function (v) { v = parseFloat(String(v || '').replace(/,/g, '')); return isNaN(v) ? 0 : v; };
    function sum() {
        var a = ins.reduce(function (t, i) { return t + num(i.value); }, 0), total = num(amt.value);
        outA.textContent = a.toFixed(2);
        outC.textContent = Math.max(0, total - a).toFixed(2);
        outA.style.color = a > total + 0.004 ? 'var(--bad)' : '';
    }
    function share() {
        var left = num(amt.value);
        ins.forEach(function (i) { var b = num(i.getAttribute('data-fp-bal')), x = Math.min(b, Math.max(0, left)); i.value = x > 0 ? x.toFixed(2) : ''; left -= x; });
        sum();
    }
    amt.addEventListener('input', function () { if (!touched) share(); else sum(); });
    ins.forEach(function (i) { i.addEventListener('input', function () { touched = true; sum(); }); });
    var auto = f.querySelector('[data-fp-auto]');
    if (auto) auto.addEventListener('click', function () { touched = false; share(); });
    if (ins.some(function (i) { return i.value !== ''; })) { touched = true; sum(); } else if (amt.value) share(); else sum();
});

// Bank reconciliation: live check of statement balance + uncleared in − uncleared out against the cash book
document.querySelectorAll('form[data-recon]').forEach(function (f) {
    var book = parseFloat(f.getAttribute('data-book')) || 0, st = f.querySelector('[data-recon-stmt]');
    var boxes = Array.prototype.slice.call(f.querySelectorAll('input[data-eff]'));
    var out = function (k, v) { var el = f.querySelector('[data-r="' + k + '"]'); if (el) el.textContent = v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); return el; };
    function calc() {
        var s = parseFloat(String(st.value).replace(/,/g, '')) || 0, i = 0, o = 0;
        boxes.forEach(function (b) { if (!b.checked) { var e = parseFloat(b.getAttribute('data-eff')) || 0; if (e > 0) i += e; else o -= e; } });
        var adj = s + i - o, diff = Math.round((book - adj) * 100) / 100;
        out('stmt', s); out('in', i); out('out', o); out('adj', adj);
        var d = out('diff', diff); d.className = Math.abs(diff) < 0.005 ? 'ok' : 'bad';
        var done = f.querySelector('[data-recon-done]'); if (done) done.disabled = Math.abs(diff) >= 0.005 || st.value === '';
    }
    st.addEventListener('input', calc);
    boxes.forEach(function (b) { b.addEventListener('change', calc); });
    var all = f.querySelector('[data-recon-all]');
    if (all) all.addEventListener('click', function () { var on = boxes.some(function (b) { return !b.checked; }); boxes.forEach(function (b) { b.checked = on; }); calc(); });
    calc();
});

// Supplier bill: show the withholding tax as the amount / type change
document.querySelectorAll('form[data-wht-form]').forEach(function (f) {
    var rates = JSON.parse(f.getAttribute('data-rates') || '{}');
    var amt = f.querySelector('[data-wht-amount]'), type = f.querySelector('[data-wht-type]'), none = f.querySelector('[data-wht-none]'), out = f.querySelector('[data-wht-out]');
    var sup = f.querySelector('[data-wht-supplier]'), cat = f.querySelector('[data-wht-cat]');
    function calc() {
        var a = parseFloat(String(amt.value).replace(/,/g, '')) || 0, r = rates[type.value] || 0;
        var w = (none.checked || a <= rates.threshold) ? 0 : Math.round(a * r) / 100;
        out.innerHTML = 'Withholding tax: <strong>' + w.toFixed(2) + '</strong>' + (w > 0 ? ' (' + r + '%) — the supplier is paid <strong>' + (a - w).toFixed(2) + '</strong>' : (a > 0 && a <= rates.threshold ? ' (bill not above GH₵ ' + rates.threshold + ')' : ''));
    }
    sup.addEventListener('change', function () {
        var o = sup.selectedOptions[0];
        if (o && o.getAttribute('data-type')) type.value = o.getAttribute('data-type');
        if (o && o.getAttribute('data-cat') && o.getAttribute('data-cat') !== '0' && !cat.value) cat.value = o.getAttribute('data-cat');
        calc();
    });
    [amt, type, none].forEach(function (el) { el.addEventListener('input', calc); el.addEventListener('change', calc); });
    if (sup.value && f.hasAttribute('data-fresh')) sup.dispatchEvent(new Event('change'));
    calc();
});
// Paying a supplier bill: WHT kept and net paid for the amount settled
document.querySelectorAll('form[data-sp-pay]').forEach(function (f) {
    var g = f.querySelector('[data-sp-gross]'), amount = parseFloat(f.getAttribute('data-amount')) || 1, wht = parseFloat(f.getAttribute('data-wht')) || 0;
    var left = parseFloat(f.getAttribute('data-left')) || 0, whtLeft = parseFloat(f.getAttribute('data-wht-left')) || 0;
    function calc() {
        var x = parseFloat(String(g.value).replace(/,/g, '')) || 0;
        var w = Math.abs(x - left) < 0.005 ? whtLeft : Math.round(x * wht / amount * 100) / 100;
        f.querySelector('[data-sp-wht]').textContent = w.toFixed(2);
        f.querySelector('[data-sp-net]').textContent = (x - w).toFixed(2);
    }
    g.addEventListener('input', calc); calc();
});

// Asset form: the category suggests a useful life
document.querySelectorAll('select[data-asset-cat]').forEach(function (s) {
    var life = s.form && s.form.querySelector('[data-asset-life]');
    if (!life) return;
    s.addEventListener('change', function () { var o = s.selectedOptions[0]; if (o) life.value = o.getAttribute('data-life'); });
});

// Show a block only for chosen values: <div data-show-if="field=a,b">
(function () {
    var blocks = document.querySelectorAll('[data-show-if]');
    if (!blocks.length) return;
    var sync = function () {
        blocks.forEach(function (b) {
            var p = b.getAttribute('data-show-if').split('='), f = b.closest('form');
            var src = f && f.querySelector('[name="' + p[0] + '"]');
            if (src) b.hidden = p[1].split(',').indexOf(src.value) < 0;
        });
    };
    document.querySelectorAll('select, input').forEach(function (el) { el.addEventListener('change', sync); });
})();

// Modules on/off (School setup): the On / Off label follows the switch
(function () {
    document.querySelectorAll('.mod-row input[type=checkbox]').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var b = cb.closest('.mod-row').querySelector('[data-state]');
            if (!b) return;
            b.textContent = cb.checked ? 'On' : 'Off';
            b.className = 'badge ' + (cb.checked ? 'badge-good' : 'badge-bad');
        });
    });
})();

// Analytic Dashboard tabs (Today / School growth): open the tab this person used last time on this computer
(function () {
    var root = document.querySelector('.an[data-tabs]');
    if (!root) return;
    var key = 'nisdasDashTab';
    var tab = root.getAttribute('data-tab');
    var params = new URLSearchParams(location.search);
    try {
        if (!params.has('tab') && tab === 'today' && localStorage.getItem(key) === 'growth') {
            params.set('tab', 'growth');
            location.replace(location.pathname + '?' + params.toString());
            return;
        }
        localStorage.setItem(key, tab);
    } catch (e) { /* storage blocked: just stay on this tab */ }
    root.querySelectorAll('[data-tab-link]').forEach(function (a) {
        a.addEventListener('click', function () { try { localStorage.setItem(key, a.getAttribute('data-tab-link')); } catch (e) {} });
    });
})();

// Printable pages (A4 report cards, class lists, ID cards, registers) on a phone: shrink the page to fit the
// screen instead of scrolling sideways; printing always uses the real size.
(function () {
    if (!document.querySelector('.toolbar [data-print]')) return;
    var parts = function () { return Array.prototype.filter.call(document.body.children, function (el) { return !el.matches('.toolbar, script'); }); };
    var set = function (z) { parts().forEach(function (el) { el.style.zoom = z; }); };
    var fit = function () {
        set('');
        var need = document.documentElement.scrollWidth, have = window.innerWidth;
        if (need > have + 1) set(String(Math.max(0.3, (have - 4) / need)));
    };
    window.addEventListener('beforeprint', function () { set(''); });
    window.addEventListener('afterprint', fit);
    window.addEventListener('resize', fit);
    fit();
})();

// In-page tabs: <div data-ptabs> with buttons [data-ptab="x"] and panels [data-ppanel="x"]; the open tab is kept in the address (#x).
(function () {
    document.querySelectorAll('[data-ptabs]').forEach(function (root) {
        var own = function (sel) { return Array.prototype.slice.call(root.querySelectorAll(sel)).filter(function (el) { return el.closest('[data-ptabs]') === root; }); };
        var tabs = own('[data-ptab]');
        var local = root.hasAttribute('data-ptabs-local'); // tabs inside tabs: do not touch the address
        var show = function (name, focus) {
            tabs.forEach(function (t) {
                var on = t.getAttribute('data-ptab') === name;
                t.setAttribute('aria-selected', on ? 'true' : 'false');
                t.tabIndex = on ? 0 : -1;
                if (on && focus) t.focus();
            });
            own('[data-ppanel]').forEach(function (p) { p.hidden = p.getAttribute('data-ppanel') !== name; });
        };
        tabs.forEach(function (t, i) {
            t.addEventListener('click', function () {
                show(t.getAttribute('data-ptab'));
                if (!local) { try { history.replaceState(null, '', '#' + t.getAttribute('data-ptab')); } catch (e) {} }
            });
            t.addEventListener('keydown', function (ev) {
                var d = ev.key === 'ArrowRight' ? 1 : ev.key === 'ArrowLeft' ? -1 : 0;
                if (!d) return;
                ev.preventDefault();
                var n = tabs[(i + d + tabs.length) % tabs.length];
                n.click(); n.focus();
            });
        });
        var h = local ? '' : location.hash.slice(1);
        if (h && tabs.some(function (t) { return t.getAttribute('data-ptab') === h; })) show(h);
    });
})();

// Grading setup: class % + exam % always make 100; the bar shows the split; "+ Add a row" reveals a spare row; × clears a row.
(function () {
    document.querySelectorAll('[data-weights]').forEach(function (box) {
        var c = box.querySelector('[name="class_weight"]'), x = box.querySelector('[name="exam_weight"]');
        var bc = box.querySelector('[data-bar-class]'), bx = box.querySelector('[data-bar-exam]');
        var draw = function () { var v = Math.max(0, Math.min(100, parseInt(c.value || '0', 10))); bc.style.width = v + '%'; bx.style.width = (100 - v) + '%'; bc.textContent = v + '%'; bx.textContent = (100 - v) + '%'; };
        c.addEventListener('input', function () { var v = parseInt(c.value || '0', 10); if (v >= 0 && v <= 100) x.value = 100 - v; draw(); });
        x.addEventListener('input', function () { var v = parseInt(x.value || '0', 10); if (v >= 0 && v <= 100) c.value = 100 - v; draw(); });
        draw();
    });
    document.addEventListener('click', function (ev) {
        var add = ev.target.closest('[data-band-add]');
        if (add) {
            var spare = document.querySelector('#' + add.getAttribute('data-band-add') + ' tr[hidden]');
            if (spare) { spare.hidden = false; var i = spare.querySelector('input'); if (i) i.focus(); }
            if (!document.querySelector('#' + add.getAttribute('data-band-add') + ' tr[hidden]')) add.hidden = true;
            return;
        }
        var del = ev.target.closest('[data-band-del]');
        if (del) {
            var tr = del.closest('tr');
            tr.querySelectorAll('input').forEach(function (i) { i.value = ''; });
            tr.hidden = true;
            var t = tr.closest('table'), b = t && document.querySelector('[data-band-add="' + t.id + '"]');
            if (b) b.hidden = false;
        }
    });
    document.querySelectorAll('[data-show-when]').forEach(function (el) {
        var cb = document.getElementById(el.getAttribute('data-show-when'));
        if (!cb) return;
        var f = function () { el.hidden = !cb.checked; };
        cb.addEventListener('change', f); f();
    });
})();

// A link to page#id opens a section that is hidden behind its own button (e.g. payroll#rates-box)
(function () {
    var id = location.hash.slice(1);
    if (!/^[\w-]+$/.test(id)) return;
    var box = document.getElementById(id), btn = document.querySelector('[data-help="' + id + '"]');
    if (box && box.hidden && btn) { btn.click(); }
})();

// Gate pick-up: signature pad, the "someone else" fields, and the waiting screen that checks for the parent's answer
(function () {
    document.querySelectorAll('canvas[data-sigpad]').forEach(function (c) {
        var out = document.getElementById(c.getAttribute('data-sigpad')), ctx = c.getContext('2d'), drawing = false, used = false, last = null;
        ctx.lineWidth = 2.6; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#111827';
        var pos = function (e) { var r = c.getBoundingClientRect(), p = e.touches ? e.touches[0] : e; return [(p.clientX - r.left) * c.width / r.width, (p.clientY - r.top) * c.height / r.height]; };
        var start = function (e) { drawing = true; last = pos(e); e.preventDefault(); };
        var move = function (e) { if (!drawing) return; var p = pos(e); ctx.beginPath(); ctx.moveTo(last[0], last[1]); ctx.lineTo(p[0], p[1]); ctx.stroke(); last = p; used = true; e.preventDefault(); };
        var end = function () { if (drawing && used && out) out.value = c.toDataURL('image/png'); drawing = false; };
        c.addEventListener('mousedown', start); c.addEventListener('mousemove', move); window.addEventListener('mouseup', end);
        c.addEventListener('touchstart', start, { passive: false }); c.addEventListener('touchmove', move, { passive: false }); c.addEventListener('touchend', end);
        var clear = c.closest('.sigpad-wrap') && c.closest('.sigpad-wrap').querySelector('[data-sig-clear]');
        if (clear) clear.addEventListener('click', function () { ctx.clearRect(0, 0, c.width, c.height); used = false; if (out) out.value = ''; });
        var form = c.closest('form');
        if (form) form.addEventListener('submit', function (e) { if (!out || !out.value) { e.preventDefault(); c.classList.add('is-missing'); c.scrollIntoView({ block: 'center' }); } });
    });
    document.querySelectorAll('[data-gate-form]').forEach(function (f) {
        var other = f.querySelector('[data-gate-other]'), photo = f.querySelector('[data-gate-photo]'), code = f.querySelector('[data-gate-code]');
        var sync = function () {
            var r = f.querySelector('input[name="who"]:checked'), k = r ? r.getAttribute('data-gate-kind') : 'other';
            if (other) { other.hidden = k !== 'other'; var n = other.querySelector('[name="collector_name"]'); if (n) n.required = k === 'other'; }
            if (photo) { var i = photo.querySelector('input'); if (i) i.required = k !== 'guardian'; }
            if (code) code.hidden = k === 'guardian';
        };
        f.querySelectorAll('input[name="who"]').forEach(function (r) { r.addEventListener('change', sync); });
        sync();
    });
    var poll = document.querySelector('[data-gate-poll]');
    if (poll) {
        var url = poll.getAttribute('data-gate-poll');
        setInterval(function () {
            fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); })
                .then(function (j) { if (j && j.status && j.status !== 'waiting') location.reload(); }).catch(function () {});
        }, 6000);
    }
})();
