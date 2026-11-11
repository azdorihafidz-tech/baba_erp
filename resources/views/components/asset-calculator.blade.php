{{--
    Kalkulator sederhana — murni alat bantu hitung di form Aset (Tambah/Edit),
    TIDAK terhubung ke field manapun (isolated). Pure vanilla JS, tanpa eval(),
    tanpa library baru. Reusable via @include supaya tidak duplikat JS di
    create.blade.php dan edit.blade.php.
--}}
<div class="card mt-3">
    <div class="card-header py-2">
        <span class="fw-semibold small">🧮 Kalkulator</span>
    </div>
    <div class="card-body p-2" style="max-width:260px; margin:0 auto;">
        <input type="text" id="calcDisplay" class="form-control form-control-lg text-end mb-2"
            value="0" readonly tabindex="-1" style="font-variant-numeric: tabular-nums;">
        <div class="d-grid gap-1" style="grid-template-columns: repeat(4, 1fr); display: grid !important;">
            <button type="button" class="btn btn-outline-danger btn-sm" data-calc-action="clear">C</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-calc-action="backspace"><i class="bi bi-backspace"></i></button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-calc-action="percent">%</button>
            <button type="button" class="btn btn-primary btn-sm" data-calc-op="÷">÷</button>

            <button type="button" class="btn btn-outline-dark btn-sm" data-calc-digit="7">7</button>
            <button type="button" class="btn btn-outline-dark btn-sm" data-calc-digit="8">8</button>
            <button type="button" class="btn btn-outline-dark btn-sm" data-calc-digit="9">9</button>
            <button type="button" class="btn btn-primary btn-sm" data-calc-op="×">×</button>

            <button type="button" class="btn btn-outline-dark btn-sm" data-calc-digit="4">4</button>
            <button type="button" class="btn btn-outline-dark btn-sm" data-calc-digit="5">5</button>
            <button type="button" class="btn btn-outline-dark btn-sm" data-calc-digit="6">6</button>
            <button type="button" class="btn btn-primary btn-sm" data-calc-op="−">−</button>

            <button type="button" class="btn btn-outline-dark btn-sm" data-calc-digit="1">1</button>
            <button type="button" class="btn btn-outline-dark btn-sm" data-calc-digit="2">2</button>
            <button type="button" class="btn btn-outline-dark btn-sm" data-calc-digit="3">3</button>
            <button type="button" class="btn btn-primary btn-sm" data-calc-op="+">+</button>

            <button type="button" class="btn btn-outline-dark btn-sm" data-calc-digit="0" style="grid-column: span 2;">0</button>
            <button type="button" class="btn btn-outline-dark btn-sm" data-calc-digit=".">.</button>
            <button type="button" class="btn btn-success btn-sm" data-calc-action="equal">=</button>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
// ---- Kalkulator sederhana, isolated dari form (murni alat bantu) ----
// Vanilla JS, tanpa eval() (keamanan) -- state machine standar kalkulator:
// currentValue (angka yang sedang diketik) + previousValue + operator pending.
(function () {
    document.querySelectorAll('#calcDisplay').forEach(function (display) {
        const wrapper = display.closest('.card');
        if (!wrapper || wrapper._calcInit) return;
        wrapper._calcInit = true;

        let current = '0';
        let previous = null;
        let operator = null;
        let resetOnNextInput = false;

        function render() {
            display.value = current;
        }

        function inputDigit(d) {
            if (resetOnNextInput) {
                current = (d === '.') ? '0.' : d;
                resetOnNextInput = false;
                render();
                return;
            }
            if (d === '.') {
                if (!current.includes('.')) current += '.';
            } else if (current === '0') {
                current = d;
            } else {
                current += d;
            }
            render();
        }

        function compute(a, b, op) {
            a = parseFloat(a); b = parseFloat(b);
            switch (op) {
                case '+': return a + b;
                case '−': return a - b;
                case '×': return a * b;
                case '÷': return b === 0 ? 0 : a / b;
                default: return b;
            }
        }

        function setOperator(op) {
            if (operator && !resetOnNextInput) {
                previous = compute(previous, current, operator);
                current = String(previous);
            } else {
                previous = current;
            }
            operator = op;
            resetOnNextInput = true;
            render();
        }

        function equal() {
            if (operator === null || previous === null) return;
            current = String(compute(previous, current, operator));
            operator = null;
            previous = null;
            resetOnNextInput = true;
            render();
        }

        function clearAll() {
            current = '0'; previous = null; operator = null; resetOnNextInput = false;
            render();
        }

        function backspace() {
            if (resetOnNextInput) return;
            current = current.length > 1 ? current.slice(0, -1) : '0';
            render();
        }

        function percent() {
            current = String(parseFloat(current || '0') / 100);
            render();
        }

        wrapper.querySelectorAll('[data-calc-digit]').forEach(function (btn) {
            btn.addEventListener('click', function () { inputDigit(this.dataset.calcDigit); });
        });
        wrapper.querySelectorAll('[data-calc-op]').forEach(function (btn) {
            btn.addEventListener('click', function () { setOperator(this.dataset.calcOp); });
        });
        wrapper.querySelector('[data-calc-action="equal"]')?.addEventListener('click', equal);
        wrapper.querySelector('[data-calc-action="clear"]')?.addEventListener('click', clearAll);
        wrapper.querySelector('[data-calc-action="backspace"]')?.addEventListener('click', backspace);
        wrapper.querySelector('[data-calc-action="percent"]')?.addEventListener('click', percent);

        // Keyboard support -- hanya saat display kalkulator dalam viewport fokus tidak dibutuhkan;
        // scoped ke document supaya bisa dipakai tanpa klik dulu, tapi diabaikan kalau user
        // sedang mengetik di field form lain (supaya tidak ganggu input form).
        document.addEventListener('keydown', function (e) {
            const active = document.activeElement;
            const isFormField = active && ['INPUT', 'TEXTAREA', 'SELECT'].includes(active.tagName) && active.id !== 'calcDisplay';
            if (isFormField) return;

            if (e.key >= '0' && e.key <= '9') { inputDigit(e.key); e.preventDefault(); }
            else if (e.key === '.') { inputDigit('.'); e.preventDefault(); }
            else if (e.key === '+') { setOperator('+'); e.preventDefault(); }
            else if (e.key === '-') { setOperator('−'); e.preventDefault(); }
            else if (e.key === '*') { setOperator('×'); e.preventDefault(); }
            else if (e.key === '/') { setOperator('÷'); e.preventDefault(); }
            else if (e.key === '%') { percent(); e.preventDefault(); }
            else if (e.key === 'Enter' || e.key === '=') { equal(); e.preventDefault(); }
            else if (e.key === 'Backspace') { backspace(); e.preventDefault(); }
            else if (e.key === 'Escape') { clearAll(); e.preventDefault(); }
        });
    });
})();
</script>
@endpush
@endonce
