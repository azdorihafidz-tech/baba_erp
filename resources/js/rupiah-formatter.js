/**
 * Rupiah Formatter — ERP D'mentai
 *
 * Auto-formats money inputs with dot thousand separators.
 * Usage: add [data-rupiah] to any <input> element.
 *
 * Two patterns supported:
 *  1) Direct (strip-on-submit):
 *     <input type="text" data-rupiah name="harga" value="5000000">
 *     On submit: value sent as "5000000" (dots stripped automatically)
 *
 *  2) Component (hidden input):
 *     <input type="text" data-rupiah data-rupiah-for="harga" value="5.000.000">
 *     <input type="hidden" name="harga" value="5000000">
 *     Hidden input stays in sync; display input value is always formatted.
 */
window.RupiahFormatter = (function () {
    'use strict';

    /**
     * Format a value into "1.000.000" display string.
     * Handles plain integers, DB decimal strings ("5000000.00"),
     * and already-formatted strings ("5.000.000").
     */
    function fmt(v) {
        v = String(v ?? '').trim();
        // DB decimal (e.g. "5000000.00"): one dot, digits only on both sides → parse as float
        if (/^\d+\.\d+$/.test(v)) {
            v = String(Math.round(parseFloat(v)));
        }
        // Strip all remaining non-digit chars (thousand-separator dots etc.)
        v = v.replace(/\D/g, '');
        return v ? v.replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '';
    }

    /** Parse a formatted string back to a plain integer. */
    function parse(v) {
        return parseInt(String(v ?? '0').replace(/\D/g, ''), 10) || 0;
    }

    /** Initialize a single [data-rupiah] input element. */
    function initEl(el) {
        if (el._rpInit) return;
        el._rpInit = true;

        // Find paired hidden input (component pattern)
        const forName = el.dataset.rupiahFor;
        const scope = el.closest('form') || el.closest('.modal') || document;
        const hiddenEl = forName
            ? scope.querySelector('input[type="hidden"][name="' + forName + '"]')
            : null;

        // Format initial value
        el.value = fmt(el.value);
        if (hiddenEl) hiddenEl.value = parse(el.value) || 0;

        // Format while typing (cursor-from-end preserves position)
        el.addEventListener('input', function () {
            const cfe = this.value.length - (this.selectionStart || 0);
            const raw = this.value.replace(/\D/g, '');
            this.value = raw ? raw.replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '';
            const nc = Math.max(0, this.value.length - cfe);
            try { this.setSelectionRange(nc, nc); } catch (_) {}
            if (hiddenEl) hiddenEl.value = raw || '0';
            // Fire custom event so external recalculate() functions can listen
            this.dispatchEvent(new Event('rupiah:change', { bubbles: true }));
        });

        // Select all on focus for easy replacement
        el.addEventListener('focus', function () {
            const self = this;
            setTimeout(function () { self.select(); }, 0);
        });
    }

    /** Initialize all [data-rupiah] inputs inside `root` (default: document). */
    function init(root) {
        (root || document).querySelectorAll('[data-rupiah]').forEach(initEl);
    }

    /** Before form submit: strip dots from direct inputs (no hidden partner). */
    function onSubmit(form) {
        form.querySelectorAll('[data-rupiah]').forEach(function (el) {
            if (!el.dataset.rupiahFor) {
                el.value = el.value.replace(/\D/g, '') || '0';
            }
        });
    }

    // Auto-init on DOM ready
    document.addEventListener('DOMContentLoaded', function () {
        init();
        document.querySelectorAll('form').forEach(function (f) {
            f.addEventListener('submit', function () { onSubmit(this); });
        });
    });

    return { fmt: fmt, parse: parse, init: init, initEl: initEl };
}());

// Short global aliases used in inline scripts
window.rupiahFmt   = window.RupiahFormatter.fmt;
window.rupiahParse = window.RupiahFormatter.parse;
