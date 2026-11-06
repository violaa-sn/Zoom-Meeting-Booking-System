{{-- Judul halaman + aksi di kanan (slot). --}}
@props(['title', 'subtitle' => null])
<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-3">
    <div>
        <h1 class="page-title">{{ $title }}</h1>
        @if ($subtitle)
            <p class="text-secondary mb-0">{{ $subtitle }}</p>
        @endif
    </div>
    <div class="d-flex gap-2 flex-wrap">{{ $slot }}</div>
</div>
