{{-- Input untuk form filter (GET). Tidak membaca old input maupun error validasi. --}}
@props([
    'label' => null,
    'name',
    'type' => 'text',
    'value' => null,
    'wrapperClass' => '',
])
@php($id = $attributes->get('id', 'filter-'.$name))
<div {{ $attributes->only('class')->merge(['class' => $wrapperClass]) }}>
    @if ($label)<label class="form-label" for="{{ $id }}">{{ $label }}</label>@endif
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ $value }}"
        {{ $attributes->except(['id', 'class'])->class(['form-control']) }}>
</div>
