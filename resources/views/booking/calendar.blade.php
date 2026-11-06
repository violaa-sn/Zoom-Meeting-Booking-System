@php
    $range = $from->locale('id')->translatedFormat('j M') . ' – ' . $to->locale('id')->translatedFormat('j M Y');
    $today = now()->format('Y-m-d');
    // Parameter yang ikut terbawa saat geser rentang: akun dan filter hari
    $keep = request('account') !== null && request('account') !== '' ? ['account' => request('account')] : [];
    if ($selectedDays !== []) {
        $keep['days'] = $selectedDays;
    }
    // Lingkaran hari seperti Google Calendar (urutan Minggu sampai Sabtu): [inisial, nama lengkap]
    $dayCircles = [
        0 => ['M', 'Minggu'],
        1 => ['S', 'Senin'],
        2 => ['S', 'Selasa'],
        3 => ['R', 'Rabu'],
        4 => ['K', 'Kamis'],
        5 => ['J', 'Jumat'],
        6 => ['S', 'Sabtu'],
    ];
    // Hari yang benar-benar tampil (setelah filter hari)
    $cols = $grids->isEmpty() ? 0 : count($grids->first()['days']);
@endphp
<x-layouts.app title="Kalender">
    <form method="GET" action="{{ route('booking.calendar') }}" class="card card-body mb-3">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-md-auto">
                <div class="row g-2">
                    <x-filter-input wrapperClass="col-6" label="Dari tanggal" name="from" type="date" :value="$from->format('Y-m-d')"
                        data-autosubmit />
                    <x-filter-input wrapperClass="col-6" label="Sampai tanggal" name="to" type="date" :value="$to->format('Y-m-d')"
                        data-autosubmit />
                </div>
            </div>
            <x-filter-select wrapperClass="col-12 col-md-4 col-xl-3" label="Pilih akun Zoom" name="account" data-autosubmit
                :value="$selectedAccount" :options="['' => 'Akun utama'] + $accounts->pluck('name', 'id')->all() + ['all' => 'Semua akun']" />
            <div class="col-12 col-md-auto">
                <span class="form-label d-block">Tampilkan hari <small class="fw-normal text-secondary">(kosong = semua hari)</small></span>
                <div class="days" role="group" aria-label="Filter hari">
                    @foreach ($dayCircles as $i => [$initial, $fullName])
                        <label class="day" title="{{ $fullName }}"><input type="checkbox" name="days[]"
                                value="{{ $i }}" @checked(in_array($i, $selectedDays, true))
                                data-autosubmit aria-label="{{ $fullName }}"><span>{{ $initial }}</span></label>
                    @endforeach
                </div>
            </div>
            <div class="col-12 d-flex flex-wrap gap-2" aria-label="Arti warna">
                <span class="chip chip--available"><i></i>Tersedia</span>
                <span class="chip chip--pending"><i></i>Menunggu persetujuan</span>
                <span class="chip chip--approved"><i></i>Disetujui</span>
                <span class="chip chip--full"><i></i>Penuh / Tidak tersedia</span>
            </div>
        </div>
        @if ($clamped)
            <x-alert type="warning" class="mt-3">Rentang dipersingkat menjadi {{ $maxDays }} hari, batas maksimal
                kalender.</x-alert>
        @endif
    </form>

    <div class="card overflow-hidden">
        <div class="px-3 py-2 border-bottom d-flex flex-wrap justify-content-between gap-1">
            <b>{{ $range }}</b>
            <span class="text-secondary">{{ $cols }} hari{{ $selectedDays !== [] ? ' dipilih' : '' }}, jam 06.00 sampai 24.00</span>
        </div>
        <div class="cal-scroll">
            @if ($grids->isEmpty())
                <div class="p-5 text-center text-secondary">Belum ada akun Zoom aktif. Minta admin menambahkannya di
                    menu Akun Zoom.</div>
            @elseif ($cols === 0)
                <div class="p-5 text-center text-secondary">Tidak ada tanggal pada hari yang dipilih di rentang ini.
                    Ubah rentang tanggal atau filter harinya.</div>
            @else
                <div class="cal-grid" style="--cols: {{ $cols }}">
                    <div class="cal-head">
                        <div>Waktu</div>
                        @foreach ($grids->first()['days'] as $day)
                            <div @class(['is-today' => $day['date']->format('Y-m-d') === $today])>
                                <span class="cal-day">{{ $day['name'] }}</span>
                                <span class="cal-date">{{ $day['date']->locale('id')->translatedFormat('j M Y') }}</span>
                            </div>
                        @endforeach
                    </div>

                    @foreach ($grids as $grid)
                        @php
                            $account = $grid['account'];
                            $capacity = max(1, (int) $account->allowed_login_users);
                        @endphp
                        <div class="cal-acct">
                            <span class="acct-dot" style="--c: {{ $account->color ?: '#3b6ee0' }}"><x-icon
                                    name="video" :size="16" /></span>
                            <h2>{{ $account->name }}</h2>
                            <x-badge tone="blue">Kapasitas {{ $capacity }} meeting</x-badge>
                            @if ($account->is_default)
                                <x-badge>Akun utama</x-badge>
                            @endif
                        </div>

                        @foreach (array_keys($grid['days'][0]['slots']) as $i)
                            @php $ref = $grid['days'][0]['slots'][$i]; @endphp
                            <div class="cal-row">
                                <div class="cal-time">{{ str_replace(':', '.', $ref['start']) }} –
                                    {{ str_replace(':', '.', $ref['end']) }}</div>
                                @foreach ($grid['days'] as $day)
                                    @php
                                        $s = $day['slots'][$i];
                                        // Kotak kosong tapi tidak bisa dipilih (akun nonaktif / langganan habis):
                                        // tampil sebagai "Tidak tersedia" dengan warna merah
                                        $closed = ! $s['bookable'] && ! $s['past'] && $s['status'] === 'available';
                                        $state = $closed ? 'full' : $s['status'];
                                        // Hanya satu kata sesuai status, tanpa nama peminjam
                                        $text = match (true) {
                                            $s['status'] === 'full' => 'Penuh',
                                            $s['status'] === 'approved' => 'Disetujui',
                                            $s['status'] === 'pending' => 'Menunggu',
                                            $s['past'] => 'Lewat',
                                            $closed => 'Tidak tersedia',
                                            default => 'Tersedia',
                                        };
                                        $cls =
                                            'slot slot--' .
                                            $state .
                                            ($s['past'] ? ' is-past' : '') .
                                            (!$s['bookable'] && !$s['past'] ? ' is-closed' : '');
                                        $tip =
                                            $day['date']->locale('id')->translatedFormat('j M Y') .
                                            ', ' .
                                            $s['start'] .
                                            '–' .
                                            $s['end'];
                                    @endphp
                                    @if ($s['bookable'])
                                        <button type="button" class="{{ $cls }}" title="{{ $tip }}"
                                            data-slot data-bs-toggle="offcanvas" data-bs-target="#booking-panel"
                                            data-date="{{ $day['date']->format('Y-m-d') }}"
                                            data-start="{{ $s['start'] }}">{{ $text }}</button>
                                    @else
                                        <div class="{{ $cls }}" title="{{ $tip }}">
                                            {{ $text }}</div>
                                    @endif
                                @endforeach
                            </div>
                        @endforeach
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="row g-3 mt-1 align-items-center">
        <div class="col-lg">
            <div class="note d-flex gap-3 p-3">
                <x-icon name="info" :size="22" class="mt-1" />
                <div>
                    <b>Informasi penting</b>
                    <ul class="mb-0 ps-3 mt-1">
                        <li>Satu akun Zoom hanya dipakai untuk satu meeting pada satu waktu, kecuali kapasitas akunnya
                            lebih dari 1.</li>
                        <li>Klik kotak hijau untuk mengisi tanggal dan jam otomatis di formulir.</li>
                        <li>Kotak kalender dibagi per 30 menit hanya sebagai tampilan. Booking boleh jam berapa saja
                            (misalnya 09.12 sampai 10.24), dan kotak yang terisi sebagian tetap tampil terisi.</li>
                        <li>"Lewat" artinya waktunya sudah terlewati.</li>
                        <li>Booking baru berlaku setelah disetujui admin. Admin juga yang menentukan akun Zoom yang
                            dipakai.</li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-xl-3 text-center">
            <x-button size="lg" icon="calendar" class="w-100" data-bs-toggle="offcanvas"
                data-bs-target="#booking-panel">Ajukan booking Zoom</x-button>
            <p class="text-secondary small mt-2 mb-0">Pilih jadwal yang tersedia, lalu isi formulir pengajuan.</p>
        </div>
    </div>
</x-layouts.app>
