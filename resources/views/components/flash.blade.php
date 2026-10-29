{{-- Jembatan pesan session -> SweetAlert. Taruh sekali di layout. --}}
@foreach (['success', 'error', 'warning'] as $type)
    @if (session($type))<div hidden data-flash="{{ $type }}" data-message="{{ session($type) }}"></div>@endif
@endforeach
