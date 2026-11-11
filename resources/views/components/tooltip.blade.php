@props(['key', 'placement' => 'top', 'icon' => 'question-circle'])

@php
    $tooltip = \App\Models\Tooltip::get($key);
@endphp

@if($tooltip)
    @php
        $tipContent = $tooltip->title
            ? '<strong>' . e($tooltip->title) . '</strong><br>' . e($tooltip->content)
            : e($tooltip->content);
    @endphp
    <span
        class="ms-1 tooltip-helper"
        style="cursor:pointer;color:#9ca3af;font-size:.82em;vertical-align:middle;line-height:1"
        data-bs-toggle="tooltip"
        data-bs-placement="{{ $placement }}"
        data-bs-html="true"
        data-bs-title="{!! $tipContent !!}"
        tabindex="0"
        role="button"
        aria-label="{{ $tooltip->title ?? 'Info' }}"
    ><i class="bi bi-{{ $icon }}"></i></span>
@endif
