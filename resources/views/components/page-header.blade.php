{{-- Judul halaman + aksi di kanan (slot). :count menampilkan badge total di samping judul. --}}
@props(['title', 'subtitle' => null, 'count' => null])
<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-3">
    <div>
        <h1 class="page-title d-flex align-items-center gap-2">{{ $title }}
            @if ($count !== null)
                <x-badge tone="blue" title="Total {{ strtolower($title) }}">{{ number_format($count, 0, ',', '.') }}</x-badge>
            @endif
        </h1>
        @if ($subtitle)
            <p class="text-secondary mb-0">{{ $subtitle }}</p>
        @endif
    </div>
    <div class="d-flex gap-2 flex-wrap">{{ $slot }}</div>
</div>
