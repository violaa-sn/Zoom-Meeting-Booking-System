{{-- Dropdown untuk form filter (GET). Tidak membaca old input maupun error validasi, jadi tidak bentrok
     dengan form tambah/ubah yang memakai name yang sama (mis. department_id). Gunakan :options="[nilai => label]". --}}
@props([
    'label' => null,
    'name',
    'options' => [],
    'value' => null,
    'placeholder' => 'Semua',
    'wrapperClass' => '',
])
@php
    $id = $attributes->get('id', 'filter-'.$name);
    $current = (string) $value;
@endphp
<div {{ $attributes->only('class')->merge(['class' => $wrapperClass]) }}>
    @if ($label)<label class="form-label" for="{{ $id }}">{{ $label }}</label>@endif
    <select id="{{ $id }}" name="{{ $name }}" {{ $attributes->except(['id', 'class'])->class(['form-select']) }}>
        <option value="">{{ $placeholder }}</option>
        @foreach ($options as $optValue => $optLabel)
            <option value="{{ $optValue }}" @selected($current === (string) $optValue)>{{ $optLabel }}</option>
        @endforeach
    </select>
</div>
