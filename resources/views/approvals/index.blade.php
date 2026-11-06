@php
    $units = ['day' => 'hari', 'week' => 'minggu', 'month' => 'bulan', 'year' => 'tahun'];
    $dayShort = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
@endphp
<x-layouts.app title="Persetujuan">
    <x-page-header title="Persetujuan" :subtitle="$bookings->total() . ' pengajuan menunggu keputusan.'" />

    <form method="GET" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <x-filter-input wrapperClass="col-12 col-md-5 col-xl-4" label="Cari peminjam" name="search" :value="request('search')"
                placeholder="Nama atau username" />
            <x-filter-select wrapperClass="col-12 col-md-4 col-xl-3" label="Tipe akun diminta" name="account_type"
                :value="request('account_type')" placeholder="Semua tipe" :options="$accountTypes" />
            <div class="col-12 col-md-auto filter-actions">
                <x-button type="submit" variant="secondary" icon="search" class="flex-fill">Terapkan</x-button>
                <x-button href="{{ route('approvals.index') }}" variant="ghost">Atur ulang</x-button>
            </div>
        </div>
    </form>

    <div class="card overflow-hidden">
        <div class="table-responsive">
            <table class="table table-stack mb-0">
                <thead>
                    <tr>
                        <th>Peminjam</th>
                        <th>Jadwal</th>
                        <th>Tipe akun diminta</th>
                        <th>Keperluan</th>
                        <th class="text-end">Keputusan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($bookings as $b)
                        @php
                            $i = $info[$b->id];
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
                            }
                            $summary = $b->user->name . ', ' . $i['range'] . ', ' . substr($b->start_time, 0, 5) . ' – ' . substr($b->end_time, 0, 5);
                        @endphp
                        <tr>
                            <td class="td-first" data-label="Peminjam">
                                <div class="cell-main">
                                    <b>{{ $b->user->name }}</b><small>{{ $b->user->department?->name ?? 'Tanpa departemen' }},
                                        diajukan {{ $b->created_at->locale('id')->diffForHumans() }}</small></div>
                            </td>
                            <td data-label="Jadwal">
                                <div class="cell-main">
                                    <b>{{ $i['range'] }}</b><small>{{ substr($b->start_time, 0, 5) }} –
                                        {{ substr($b->end_time, 0, 5) }}, {{ $repeat ?? 'sekali' }}@if ($i['count'] > 1)
                                            , {{ $i['count'] }} tanggal
                                        @endif
                                    </small>
                                </div>
                            </td>
                            <td data-label="Tipe akun diminta">
                                <x-badge tone="blue">{{ ucfirst($b->account_type) }}</x-badge>
                            </td>
                            <td data-label="Keperluan">
                                <div class="cell-main">
                                    <b>{{ $b->purpose === 'lainnya' ? ($b->purpose_other ?: 'Lainnya') : ucfirst($b->purpose) }}</b><small>{{ $b->participantLabel() }}
                                        peserta</small></div>
                            </td>
                            <td data-label="Keputusan">
                                @if ($i['expired'])
                                    {{-- Semua tanggal sudah lewat: tidak bisa disetujui maupun ditolak --}}
                                    <div class="text-end text-secondary small fw-semibold">Tanggal booking sudah lewat</div>
                                @else
                                    <div class="d-flex gap-2 justify-content-end">
                                        {{-- Setujui membuka daftar rekomendasi akun; admin yang memilih akunnya --}}
                                        <x-button variant="success" size="sm" icon="check" data-bs-toggle="modal"
                                            data-bs-target="#approve-modal"
                                            data-url="{{ route('approvals.recommend', $b) }}"
                                            data-action="{{ route('approvals.approve', $b) }}"
                                            data-summary="{{ $summary }}">Setujui</x-button>
                                        <x-button variant="secondary" size="sm" icon="x" data-bs-toggle="modal"
                                            data-bs-target="#reject-modal"
                                            data-action="{{ route('approvals.reject', $b) }}"
                                            data-summary="{{ $summary }}">Tolak</x-button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" data-label="">
                                <div class="p-5 text-center text-secondary">Tidak ada pengajuan yang menunggu. Semua
                                    sudah diproses.</div>
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

    @error('reason')
        <div hidden data-flash="error" data-message="{{ $message }}"></div>
    @enderror
    @error('zoom_account_id')
        <div hidden data-flash="error" data-message="{{ $message }}"></div>
    @enderror

    <x-slot:extra>
        <x-modal id="approve-modal" title="Pilih akun Zoom"
            subtitle="Pilih akun yang akan dipakai peminjam. Hanya akun bertipe yang diminta yang muncul.">
            <form method="POST" action="#">
                @csrf
                <div class="modal-body d-grid gap-3">
                    <p class="mb-0 fw-semibold" data-approve-summary></p>
                    <div class="d-grid gap-2" data-approve-accounts></div>
                    <x-alert type="warning" title="Tidak ada akun yang kosong" data-approve-none hidden>Semua akun
                        bertipe ini penuh pada sebagian tanggal. Tolak pengajuan dan minta peminjam mengubah jadwal,
                        atau batalkan booking lain yang bentrok dulu.</x-alert>
                </div>
                <div class="modal-footer">
                    <x-button variant="secondary" data-bs-dismiss="modal">Batal</x-button>
                    <x-button type="submit" variant="success" icon="check" data-approve-submit disabled>Setujui dengan
                        akun ini</x-button>
                </div>
            </form>
        </x-modal>

        <x-modal id="reject-modal" title="Tolak pengajuan" subtitle="Alasan akan terlihat oleh peminjam.">
            <form method="POST" action="#">
                @csrf
                <div class="modal-body d-grid gap-3">
                    <p class="mb-0 fw-semibold" data-reject-summary></p>
                    <div>
                        <label class="form-label" for="reason">Alasan penolakan<em>*</em></label>
                        <textarea id="reason" name="reason" class="form-control" rows="3" maxlength="500" required
                            placeholder="Contoh: jadwal bentrok dengan kegiatan lain"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <x-button variant="secondary" data-bs-dismiss="modal">Batal</x-button>
                    <x-button type="submit" variant="danger" icon="x">Tolak pengajuan</x-button>
                </div>
            </form>
        </x-modal>
    </x-slot:extra>
</x-layouts.app>
