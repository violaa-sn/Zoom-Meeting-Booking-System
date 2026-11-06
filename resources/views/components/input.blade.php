@props([
    'label' => null,
    'name',
    'type' => 'text',
    'hint' => null,
    'value' => null,
    'required' => false,
    'wrapperClass' => '',
])
@php
    $id = $attributes->get('id', $name);
    $invalid = $errors->has($name);
    $shown = old($name, $value);
@endphp
<x-field :label="$label" :name="$name" :hint="$hint" :required="$required" :for="$id" :class="$wrapperClass">
    @if ($type === 'password')
        <div class="password-field">
    @endif
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" @required($required)
        @if ($type !== 'password') value="{{ $shown }}" @endif
        {{ $attributes->except('id')->class(['form-control', 'is-invalid' => $invalid]) }}>
    @if ($type === 'password')
        <button type="button" class="password-toggle" data-password-toggle aria-label="Tampilkan password"
            aria-pressed="false">
            <x-icon name="eye" data-eye-on /><x-icon name="eye-off" data-eye-off hidden />
        </button>
        </div>
    @endif
</x-field>
