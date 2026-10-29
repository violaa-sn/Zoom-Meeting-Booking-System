@props(['label', 'name', 'checked' => false, 'hint' => null])
<div>
    <div class="form-check">
        <input type="hidden" name="{{ $name }}" value="0">
        <input class="form-check-input" type="checkbox" id="{{ $name }}" name="{{ $name }}" value="1" @checked(old($name, $checked)) {{ $attributes }}>
        <label class="form-check-label fw-semibold" for="{{ $name }}">{{ $label }}</label>
    </div>
    @if ($hint)<div class="form-text">{{ $hint }}</div>@endif
</div>
