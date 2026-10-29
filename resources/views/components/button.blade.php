{{-- variant: primary | secondary | danger | success | ghost. size: sm | md | lg. Beri :href untuk jadi link. --}}
@props(['variant' => 'primary', 'size' => 'md', 'icon' => null, 'href' => null, 'type' => 'button'])
@php
    $cls = 'btn d-inline-flex align-items-center justify-content-center gap-2 '.[
        'primary' => 'btn-primary', 'secondary' => 'btn-outline-secondary', 'danger' => 'btn-danger',
        'success' => 'btn-success', 'ghost' => 'btn-light',
    ][$variant].['sm' => ' btn-sm', 'lg' => ' btn-lg', 'md' => ''][$size];
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $cls]) }}>@if ($icon)<x-icon :name="$icon" :size="$size === 'sm' ? 15 : 17" />@endif{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $cls]) }}>@if ($icon)<x-icon :name="$icon" :size="$size === 'sm' ? 15 : 17" />@endif{{ $slot }}</button>
@endif
