{{--
    Reusable Rupiah input component.

    Usage:
        <x-input-rupiah name="harga" label="Harga Satuan" :value="old('harga', $item->harga)" required />
        <x-input-rupiah name="gaji" label="Gaji Pokok" size="sm" hint="Gaji sebelum potongan" />

    Props:
        name        – input name (required)
        label       – label text (optional; omit if you render label outside)
        value       – initial value; accepts plain int, DB decimal string, or formatted string
        size        – Bootstrap size suffix: '' | 'sm' | 'lg'
        hint        – small help text below the input
        id          – override the generated input id (pass through $attributes otherwise)

    All other HTML attributes (required, readonly, disabled, placeholder,
    id, oninput, onchange, class …) are forwarded to the visible <input>.
--}}
@props([
    'name',
    'label' => null,
    'value' => 0,
    'size'  => '',
    'hint'  => null,
])

@php
    // Safely parse any value format to a plain integer
    $strVal = (string)($value ?? 0);
    if (preg_match('/^\d+\.\d{1,2}$/', $strVal)) {
        // DB decimal like "5000000.00" → round to integer
        $rawValue = (int) round((float) $strVal);
    } else {
        // Already formatted ("5.000.000") or plain integer ("5000000") → strip non-digits
        $rawValue = (int) preg_replace('/\D/', '', $strVal);
    }
    $displayValue = $rawValue ? number_format($rawValue, 0, ',', '.') : '';

    // Determine id: prefer explicitly passed id attribute, else generate one
    $inputId = $attributes->get('id') ?? ('rp_' . preg_replace('/\W/', '_', $name) . '_' . substr(uniqid(), -5));

    $hasError = isset($errors) && $errors->has($name);

    $igClass  = 'input-group' . ($size ? ' input-group-' . $size : '');
    $fcBase   = 'form-control' . ($size ? ' form-control-' . $size : '');
    $fcError  = $hasError ? ' is-invalid' : '';
@endphp

@if ($label)
<label for="{{ $inputId }}" class="form-label fw-semibold">
    {{ $label }}
    @if ($attributes->has('required'))<span class="text-danger ms-1">*</span>@endif
</label>
@endif

<div class="{{ $igClass }}">
    <span class="input-group-text bg-white text-muted">Rp</span>
    <input
        type="text"
        inputmode="numeric"
        data-rupiah
        data-rupiah-for="{{ $name }}"
        id="{{ $inputId }}"
        value="{{ $displayValue }}"
        autocomplete="off"
        {{ $attributes->except('id')->merge(['class' => $fcBase . $fcError, 'placeholder' => '0']) }}
    >
    <input type="hidden" name="{{ $name }}" value="{{ $rawValue }}">
    @if ($hasError)
        <div class="invalid-feedback">{{ $errors->first($name) }}</div>
    @endif
</div>

@if ($hint)
<div class="form-text text-muted small">{{ $hint }}</div>
@endif
