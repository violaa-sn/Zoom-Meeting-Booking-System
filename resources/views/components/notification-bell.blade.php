{{-- Lonceng notifikasi di header: jumlah belum dibaca + 8 terbaru. --}}
@php
    $me = auth()->user();
    $unread = $me->bookingNotifications()->unread()->count();
    $latest = $me->bookingNotifications()->latest()->limit(8)->get();
    $tones = ['approved' => 'green', 'rejected' => 'red', 'cancelled' => 'grey'];
    $labels = ['approved' => 'Disetujui', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan'];
@endphp
<div class="dropdown">
    <button class="btn user-btn position-relative" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside"
        aria-expanded="false" aria-label="Notifikasi, {{ $unread }} belum dibaca">
        <x-icon name="bell" :size="20" />
        @if ($unread > 0)
            <span class="bell-badge badge rounded-pill text-bg-danger"
                data-bell-count>{{ $unread > 9 ? '9+' : $unread }}</span>
        @endif
    </button>
    <div class="dropdown-menu dropdown-menu-end shadow border-0 p-0 bell-menu">
        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
            <b>Notifikasi</b>
            @if ($unread > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button type="submit"
                        class="btn btn-link btn-sm p-0 text-decoration-none">Tandai semua dibaca</button></form>
            @endif
        </div>
        <div class="bell-list">
            @forelse ($latest as $n)
                <form method="POST" action="{{ route('notifications.read', $n) }}">
                    @csrf
                    <button type="submit" @class(['bell-item', 'is-unread' => $n->read_at === null])>
                        <span class="bell-dot" aria-hidden="true"></span>
                        <span class="flex-grow-1 text-start">
                            <span class="d-block">{{ $n->message }}</span>
                            <small class="text-secondary"><x-badge :tone="$tones[$n->type]">{{ $labels[$n->type] }}</x-badge>
                                {{ $n->created_at->locale('id')->diffForHumans() }}</small>
                        </span>
                    </button>
                </form>
            @empty
                <p class="text-center text-secondary py-4 mb-0">Belum ada notifikasi.</p>
            @endforelse
        </div>
    </div>
</div>
