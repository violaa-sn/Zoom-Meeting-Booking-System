{{-- Offcanvas Bootstrap (panel kanan). Isi slot: <form class="offcanvas-form"> dengan .offcanvas-body dan .offcanvas-foot --}}
@props(['id', 'title', 'subtitle' => null, 'open' => false])
<div id="{{ $id }}" class="offcanvas offcanvas-end" tabindex="-1" aria-labelledby="{{ $id }}-title"
    @if ($open) data-open-on-load @endif {{ $attributes }}>
    <div class="offcanvas-header align-items-start">
        <div>
            <h2 id="{{ $id }}-title" class="offcanvas-title h5 fw-bold mb-0" data-overlay-title>
                {{ $title }}</h2>
            @if ($subtitle)
                <p class="text-secondary mb-0 mt-1 small">{{ $subtitle }}</p>
            @endif
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
    </div>
    {{ $slot }}
</div>
