{{-- Pembungkus label + hint + pesan error untuk FORM tambah/ubah. Dipakai x-input, x-select.
     Untuk form filter (GET) pakai x-filter-input / x-filter-select. --}}
@props(['label' => null, 'name', 'hint' => null, 'required' => false, 'for' => null])
<div {{ $attributes }}>
    @if ($label)<label class="form-label" for="{{ $for ?? $name }}">{{ $label }}@if ($required)<em>*</em>@endif</label>@endif
    {{ $slot }}
    @if ($hint)<div class="form-text">{{ $hint }}</div>@endif
    <div class="field-error" data-error-for="{{ $name }}">@error($name){{ $message }}@enderror</div>
</div>
