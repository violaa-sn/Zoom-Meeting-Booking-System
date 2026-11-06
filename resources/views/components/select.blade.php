{{-- options: [nilai => label]. Gunakan :options="[...]" --}}
@props([
    'label' => null,
    'name',
    'options' => [],
    'value' => null,
    'placeholder' => null,
    'hint' => null,
    'required' => false,
    'wrapperClass' => '',
])
@php
    $id = $attributes->get('id', $name);
    $current = (string) old($name, $value);
    $invalid = $errors->has($name);
@endphp
<x-field :label="$label" :name="$name" :hint="$hint" :required="$required" :for="$id" :class="$wrapperClass">
    <select id="{{ $id }}" name="{{ $name }}"
        {{ $attributes->except('id')->class(['form-select', 'is-invalid' => $invalid]) }}>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optValue => $optLabel)
            <option value="{{ $optValue }}" @selected($current === (string) $optValue)>{{ $optLabel }}</option>
        @endforeach
    </select>
</x-field>
