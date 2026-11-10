(function () {
    'use strict';
    var root = document.getElementById('tsvp');
    if (!root) return;

    var euro = function (v) {
        return v.toLocaleString('de-DE', { style: 'currency', currency: 'EUR' });
    };

    function updateSumme(form) {
        if (!form || !form.hasAttribute('data-tsvp-abrechnung')) return;
        var satz = parseFloat(form.getAttribute('data-satz')) || 0;
        var h = 0;
        form.querySelectorAll('input[name="stunden[]"]').forEach(function (i) {
            h += parseFloat(String(i.value).replace(',', '.')) || 0;
        });
        form.querySelector('[data-sum-h]').textContent = h.toLocaleString('de-DE');
        form.querySelector('[data-sum-eur]').textContent = euro(h * satz);
    }

    function setMonatsgrenzen(form) {
        var m = form && form.querySelector('[name="monat"]');
        if (!m || !m.value) return;
        var p = m.value.split('-');
        var last = new Date(parseInt(p[0], 10), parseInt(p[1], 10), 0).getDate();
        form.querySelectorAll('input[name="datum[]"]').forEach(function (i) {
            i.min = m.value + '-01';
            i.max = m.value + '-' + String(last).padStart(2, '0');
        });
    }

    root.addEventListener('click', function (e) {
        var t = e.target;

        var add = t.closest('[data-add]');
        if (add) {
            var form = add.closest('form');
            var rows = form.querySelector('[data-rows]');
            var all = rows.querySelectorAll('[data-row]');
            var prev = all[all.length - 1];
            var clone = all[0].cloneNode(true);
            clone.querySelectorAll('input').forEach(function (i) { i.value = ''; });
            // Praktisch: Gruppe und Stunden aus der letzten Zeile übernehmen
            if (prev) {
                clone.querySelector('[name="stunden[]"]').value = prev.querySelector('[name="stunden[]"]').value;
                clone.querySelector('[name="taetigkeit[]"]').value = prev.querySelector('[name="taetigkeit[]"]').value;
            }
            rows.appendChild(clone);
            setMonatsgrenzen(form);
            updateSumme(form);
            clone.querySelector('input').focus();
            return;
        }

        var rm = t.closest('[data-remove]');
        if (rm) {
            var f = rm.closest('form');
            var row = rm.closest('[data-row]');
            if (f.querySelectorAll('[data-row]').length > 1) {
                row.remove();
            } else {
                row.querySelectorAll('input').forEach(function (i) { i.value = ''; });
            }
            updateSumme(f);
            return;
        }

        if (t.closest('[data-print]')) {
            window.print();
            return;
        }

        var conf = t.closest('[data-confirm]');
        if (conf && !window.confirm(conf.getAttribute('data-confirm'))) {
            e.preventDefault();
            return;
        }

        // Ganze Tabellenzeile klickbar
        var tr = t.closest('tr[data-href]');
        if (tr && !t.closest('a, button, input, select, textarea')) {
            window.location.href = tr.getAttribute('data-href');
        }
    });

    root.addEventListener('input', function (e) {
        updateSumme(e.target.closest('[data-tsvp-abrechnung]'));
    });

    root.addEventListener('change', function (e) {
        if (e.target.name === 'monat') setMonatsgrenzen(e.target.closest('form'));
        if (e.target.hasAttribute('data-sichtbarkeit')) toggleAbteilung(e.target);
    });

    function toggleAbteilung(sel) {
        var wahl = sel.closest('form').querySelector('[data-abteilung-wahl]');
        if (!wahl) return;
        var an = sel.value === 'abteilung';
        wahl.hidden = !an;
        wahl.querySelector('select').required = an;
    }

    // Doppelte Absendungen verhindern
    root.addEventListener('submit', function (e) {
        var form = e.target;
        if (form.dataset.sent) { e.preventDefault(); return; }
        form.dataset.sent = '1';
        setTimeout(function () { delete form.dataset.sent; }, 4000);
    });

    root.querySelectorAll('[data-tsvp-abrechnung]').forEach(function (f) {
        setMonatsgrenzen(f);
        updateSumme(f);
    });
    root.querySelectorAll('[data-sichtbarkeit]').forEach(toggleAbteilung);

    // Höhe der Kopfzeile merken, damit die Navigation darunter kleben bleibt
    var head = root.querySelector('.tsvp-header');
    function setHeaderHoehe() {
        if (head) root.style.setProperty('--tsvp-header-h', head.offsetHeight + 'px');
    }
    setHeaderHoehe();
    window.addEventListener('resize', setHeaderHoehe);
})();
