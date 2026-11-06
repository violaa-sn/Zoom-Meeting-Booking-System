{{-- Modal Bootstrap. Isi slot: <form> dengan .modal-body dan .modal-footer --}}
@props(['id', 'title', 'subtitle' => null, 'open' => false, 'size' => ''])
<div id="{{ $id }}" class="modal fade" tabindex="-1" aria-labelledby="{{ $id }}-title"
    aria-hidden="true" @if ($open) data-open-on-load @endif {{ $attributes }}>
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable {{ $size }}">
        <div class="modal-content">
            <div class="modal-header align-items-start">
                <div>
                    <h2 id="{{ $id }}-title" class="modal-title h5 fw-bold">{{ $title }}</h2>
                    @if ($subtitle)
                        <p class="text-secondary mb-0 mt-1 small">{{ $subtitle }}</p>
                    @endif
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            {{ $slot }}
        </div>
    </div>
</div>
