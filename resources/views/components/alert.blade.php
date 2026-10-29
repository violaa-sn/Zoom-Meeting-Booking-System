{{-- Alert inline di dalam form. Popup (sukses, konfirmasi) memakai SweetAlert lewat x-flash / x-delete-form. --}}
@props(['type' => 'info', 'title' => null])
<div {{ $attributes->class(['alert', "alert-{$type}", 'd-flex', 'gap-2', 'mb-0']) }} role="{{ $type === 'danger' ? 'alert' : 'status' }}">
    <x-icon name="info" :size="18" class="mt-1" />
    <div>@if ($title)<b class="d-block">{{ $title }}</b>@endif{{ $slot }}</div>
</div>
