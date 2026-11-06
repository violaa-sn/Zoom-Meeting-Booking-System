@php
    $units = ['day' => 'hari', 'week' => 'minggu', 'month' => 'bulan', 'year' => 'tahun'];
    $dayShort = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
    $statusOptions = [
        'pending' => 'Menunggu',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'cancelled' => 'Dibatalkan',
    ];
    $f = fn(string $k) => $filters[$k] ?? null;
@endphp
<x-layouts.app :title="$isAdmin ? 'Semua booking' : 'Booking saya'">
    <x-page-header :title="$isAdmin ? 'Semua booking' : 'Booking saya'" :subtitle="$bookings->total() . ' pengajuan ditemukan.'">
        <x-button icon="plus" data-bs-toggle="offcanvas" data-bs-target="#booking-panel">Ajukan booking</x-button>
    </x-page-header>

    <form method="GET" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <x-filter-input wrapperClass="col-12 col-md-4 col-xl-2" label="Cari" name="search" :value="$f('search')"
                :placeholder="$isAdmin ? 'Nama atau username' : 'Keterangan keperluan'" />
            <x-filter-select wrapperClass="col-6 col-md-4 col-xl-2" label="Status" name="status" :value="$f('status')"
                placeholder="Semua" :options="$statusOptions" />
            <x-filter-select wrapperClass="col-6 col-md-4 col-xl-2" label="Akun Zoom" name="zoom_account_id" :value="$f('zoom_account_id')"
                placeholder="Semua" :options="$accounts->pluck('name', 'id')->all()" />
            <x-filter-select wrapperClass="col-6 col-md-4 col-xl-2" label="Keperluan" name="purpose" :value="$f('purpose')"
                placeholder="Semua" :options="collect($purposes)->mapWithKeys(fn($p) => [$p => ucfirst($p)])->all()" />
            @if ($isAdmin)
                <x-filter-select wrapperClass="col-6 col-md-4 col-xl-2" label="Departemen" name="department_id"
                    :value="$f('department_id')" placeholder="Semua" :options="$departments->pluck('name', 'id')->all()" />
            @endif
            <div class="col-12 col-xl-auto filter-actions">
                <x-button type="submit" variant="secondary" icon="search" class="flex-fill">Terapkan</x-button>
                <x-button href="{{ route('booking.index') }}" variant="ghost">Atur ulang</x-button>
            </div>
        </div>
    </form>

    <div class="card overflow-hidden">
        <div class="table-responsive">
            <table class="table table-stack mb-0">
                <thead>
                    <tr>
                        @if ($isAdmin)
                            <th>Peminjam</th>
                        @endif
                        <th>Jadwal</th>
                        <th>Akun Zoom</th>
                        <th>Keperluan</th>
                        <th>Peserta</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($bookings as $b)
                        @php
                            $time = substr($b->start_time, 0, 5) . ' – ' . substr($b->end_time, 0, 5);
                            $repeat = null;
                            if ($b->isRecurring()) {
                                $repeat =
                                    'Setiap ' .
                                    ($b->repeat_interval > 1 ? $b->repeat_interval . ' ' : '') .
                                    $units[$b->recurrence_frequency];
                                if ($b->recurrence_frequency === 'week' && $b->days->isNotEmpty()) {
                                    $repeat .=
                                        ' (' .
                                        $b->days
                                            ->sortBy('day_of_week')
                                            ->map(fn($d) => $dayShort[$d->day_of_week])
                                            ->implode(', ') .
                                        ')';
                                }
                                $repeat .=
                                    $b->recurrence_end_type === 'on'
                                        ? ', sampai ' . $b->recurrence_ends_on->format('d/m/Y')
                                        : ', ' . $b->recurrence_ends_after . ' kali';
                            }
                        @endphp
                        <tr>
                            @if ($isAdmin)
                                <td class="td-first" data-label="Peminjam">
                                    <div class="cell-main">
                                        <b>{{ $b->user->name }}</b><small>{{ $b->user->department?->name ?? 'Tanpa departemen' }}</small>
                                    </div>
                                </td>
                            @endif
                            <td @class(['td-first' => !$isAdmin]) data-label="Jadwal">
                                <div class="cell-main">
                                    <b>{{ $b->starts_on->locale('id')->translatedFormat('D, j M Y') }},
                                        {{ $time }}</b><small>{{ $repeat ?? 'Sekali' }}</small></div>
                            </td>
                            <td data-label="Akun Zoom">
                                @if ($b->zoomAccount)
                                    {{ $b->zoomAccount->name }}
                                @else
                                    {{-- Pending: akun dipilih admin saat menyetujui --}}
                                    <span class="text-secondary">Ditentukan admin</span>
                                    @if ($b->status === 'pending')
                                        <small class="d-block text-secondary">Tipe diminta: {{ ucfirst($b->account_type) }}</small>
                                    @endif
                                @endif
                            </td>
                            <td data-label="Keperluan">
                                {{ $b->purpose === 'lainnya' ? ($b->purpose_other ?: 'Lainnya') : ucfirst($b->purpose) }}
                            </td>
                            <td data-label="Peserta">{{ $b->participantLabel() }}</td>
                            <td data-label="Status"><x-status-badge :status="$b->status" />
                                @if ($b->rejection_reason)
                                    <small class="d-block text-secondary mt-1">{{ $b->rejection_reason }}</small>
                                @endif
                            </td>
                            <td data-label="Aksi">
                                <div class="d-flex flex-wrap gap-2 justify-content-end">
                                    @if ($b->status === 'approved' && $b->has_upcoming && $b->isRecurring())
                                        <x-button variant="secondary" size="sm" icon="calendar"
                                            data-bs-toggle="modal" data-bs-target="#occurrences-modal"
                                            data-url="{{ route('booking.occurrences', $b) }}">Batalkan</x-button>
                                    @endif
                                    @if ($b->status === 'pending' || ($b->status === 'approved' && $b->has_upcoming))
                                        <form method="POST" action="{{ route('booking.cancel', $b) }}" data-confirm
                                            data-confirm-icon="warning"
                                            data-confirm-title="{{ $b->isRecurring() ? 'Batalkan semua?' : 'Batalkan booking ini?' }}"
                                            data-confirm-button="Ya, batalkan"
                                            data-confirm-text="{{ $b->status === 'approved' ? 'Semua jadwal yang belum berlangsung akan dikosongkan dan admin diberi tahu.' : 'Pengajuan ini tidak akan diproses admin.' }}">
                                            @csrf
                                            <x-button type="submit" variant="secondary" size="sm"
                                                icon="ban">{{ $b->isRecurring() ? 'Batalkan semua' : 'Batalkan' }}</x-button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isAdmin ? 7 : 6 }}" data-label="">
                                <div class="p-4 text-center text-secondary">Belum ada booking yang cocok dengan filter
                                    ini.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($bookings->hasPages())
            <div class="p-3 border-top">{{ $bookings->links() }}</div>
        @endif
    </div>

    <x-slot:extra>
        <x-modal id="occurrences-modal" title="Batalkan tanggal tertentu"
            subtitle="Pilih tanggal yang dibatalkan. Tanggal lainnya tetap berjalan.">
            <div class="modal-body p-0">
                <p class="px-3 pt-3 mb-2 fw-semibold" data-occ-account></p>
                <ul class="list-group list-group-flush" data-occ-list></ul>
            </div>
            <div class="modal-footer"><x-button variant="secondary" data-bs-dismiss="modal">Tutup</x-button></div>
        </x-modal>
    </x-slot:extra>
</x-layouts.app>
