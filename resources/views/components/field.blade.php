{{-- Pembungkus label + hint + pesan error. Dipakai x-input, x-select. --}}
@props(['label' => null, 'name', 'hint' => null, 'required' => false, 'for' => null])
<div {{ $attributes }}>
    @if ($label)<label class="form-label" for="{{ $for ?? $name }}">{{ $label }}@if ($required)<em>*</em>@endif</label>@endif
    {{ $slot }}
    @if ($hint)<div class="form-text">{{ $hint }}</div>@endif
    <div class="field-error" data-error-for="{{ $name }}">@error($name){{ $message }}@enderror</div>
</div>

