{{--
  Form booking 2 langkah dalam offcanvas Bootstrap:
  (1) isi jadwal, (2) lihat akun yang tersedia (hanya informasi: admin yang memilih akun saat menyetujui).
  Jam operasional 06:00 sampai 24:00, menit bebas.
--}}
@php
    $open = old('_form') === 'booking-form' || request()->boolean('book');
    $dayLabels = ['Mg', 'Sn', 'Sl', 'Rb', 'Km', 'Jm', 'Sb'];
    $dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    // Pilihan "Pengulangan". Teks "Selama ... minggu" dan "Selama ... bulan" ditambah hari/tanggal otomatis oleh JS
    $presetOptions = [
        'none' => 'Tidak berulang',
        'day' => 'Selama hari',
        'week' => 'Selama minggu',
        'month' => 'Selama bulan',
        'custom' => 'Kustom...',
    ];
    $unitOptions = ['day' => 'hari', 'week' => 'minggu', 'month' => 'bulan', 'year' => 'tahun'];
    $typeOptions = \App\Models\ZoomAccount::TYPES;
    $participantOptions = \App\Models\Booking::PARTICIPANT_OPTIONS;
    $purposeOptions = collect(\App\Models\Booking::PURPOSES)->mapWithKeys(fn($p) => [$p => ucfirst($p)])->all();
@endphp
<x-offcanvas id="booking-panel" title="Ajukan booking Zoom" subtitle="Admin akan mengonfirmasi setelah pengajuan masuk."
    :open="$open">
    <form id="booking-form" class="offcanvas-form" method="POST" action="{{ route('booking.store') }}" novalidate
        data-recommend-url="{{ route('booking.recommend') }}" data-step="1"
        data-out-of-hours-message="{{ config('booking.out_of_hours_message') }}">
        @csrf
        <input type="hidden" name="_form" value="booking-form">

        <div class="offcanvas-body d-grid gap-3 align-content-start">
            <div class="steps" aria-hidden="true">
                <div data-step-ind="1" class="is-active">1. Jadwal</div>
                <div data-step-ind="2">2. Akun tersedia</div>
            </div>

            <x-alert type="danger" data-form-error hidden><span data-form-error-text></span></x-alert>
            @error('operating_hours')
                <div hidden data-flash="warning" data-message="{{ $message }}"></div>
            @enderror

            {{-- Langkah 1 --}}
            <div class="d-grid gap-3" data-step-show="1">
                <div class="row g-3">
                    <x-select wrapperClass="col-sm-6" label="Keperluan" name="purpose" :options="$purposeOptions"
                        placeholder="Pilih keperluan" />
                    <x-select wrapperClass="col-sm-6" label="Jumlah peserta" name="participant_count"
                        :options="$participantOptions" placeholder="Pilih jumlah" hint="Akun yang direkomendasikan minimal mendukung jumlah ini." />
                </div>
                <div data-purpose-other><x-input label="Keterangan keperluan" name="purpose_other" maxlength="255" />
                </div>

                <x-input label="Tanggal" name="starts_on" type="date" :min="now()->format('Y-m-d')" />
                <div class="row g-3">
                    <x-input wrapperClass="col-6" label="Jam mulai" name="start_time" type="time" />
                    <x-input wrapperClass="col-6" label="Jam selesai" name="end_time" type="time" />
                    <div class="col-12 form-text mt-1">Jam booking 06.00 sampai 24.00. Menit bebas, boleh 09.12 atau
                        10.45. Selesai tengah malam: isi 00:00.</div>
                    <div class="col-12 duration-hint mt-0" data-duration hidden></div>
                </div>

                <x-select label="Tipe akun Zoom" name="account_type" :options="$typeOptions"
                    placeholder="Pilih tipe akun" :required="true" />

                <x-select label="Pengulangan" name="recurrence_preset" :options="$presetOptions" value="none" />

                {{-- Kustom: ulangi selama [angka] [satuan], hari tertentu (untuk semua satuan) --}}
                <div class="fieldset-box d-grid gap-3" data-custom hidden>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="form-label mb-0">Ulangi selama</span>
                            <input class="form-control" style="width:5.5rem" type="number" name="repeat_interval"
                                min="1" max="99" value="{{ old('repeat_interval', 1) }}" aria-label="Angka pengulangan">
                            <select class="form-select" style="width:auto" name="recurrence_unit"
                                aria-label="Satuan pengulangan">
                                @foreach ($unitOptions as $val => $label)
                                    <option value="{{ $val }}" @selected(old('recurrence_unit', 'week') === $val)>{{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field-error" data-error-for="repeat_interval"></div>
                        <div class="field-error" data-error-for="recurrence_unit"></div>
                    </div>

                    {{-- Picker hari: tampil untuk semua satuan (hari, minggu, bulan, tahun) --}}
                    <div data-days-picker hidden>
                        <span class="form-label d-block">Diulang pada hari</span>
                        <div class="days">
                            @foreach ($dayLabels as $i => $lbl)
                                <label class="day" title="{{ $dayNames[$i] }}"><input type="checkbox" name="days[]"
                                        value="{{ $i }}"
                                        @checked(in_array($i, (array) old('days', [])))><span>{{ $lbl }}</span></label>
                            @endforeach
                        </div>
                        <div class="field-error" data-error-for="days"></div>
                    </div>
                </div>

                {{-- Berakhir: tampil untuk semua pengulangan (tidak ada "Tidak pernah") --}}
                <div class="fieldset-box d-grid gap-3" data-recur hidden>
                    <div>
                        <span class="form-label d-block">Berakhir</span>
                        <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                            <div class="form-check mb-0" style="min-width:5.5rem"><input class="form-check-input"
                                    type="radio" id="end-on" name="recurrence_end_type" value="on"
                                    @checked(old('recurrence_end_type') === 'on')><label class="form-check-label"
                                    for="end-on">Pada</label></div>
                            <input class="form-control flex-grow-1" style="width:auto;min-width:9rem" type="date"
                                name="recurrence_ends_on" value="{{ old('recurrence_ends_on') }}"
                                aria-label="Tanggal berakhir">
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <div class="form-check mb-0" style="min-width:5.5rem"><input class="form-check-input"
                                    type="radio" id="end-after" name="recurrence_end_type" value="after"
                                    @checked(old('recurrence_end_type', 'after') === 'after')><label class="form-check-label"
                                    for="end-after">Setelah</label></div>
                            <input class="form-control" style="width:5.5rem" type="number"
                                name="recurrence_ends_after" min="1"
                                max="{{ config('booking.max_occurrences', 3000) }}"
                                value="{{ old('recurrence_ends_after', 4) }}" aria-label="Jumlah kejadian">
                            <span>kejadian</span>
                        </div>
                        <div class="form-text">Maksimal {{ config('booking.max_occurrences', 3000) }} tanggal.</div>
                        <div class="field-error" data-error-for="recurrence_end_type"></div>
                        <div class="field-error" data-error-for="recurrence_ends_on"></div>
                        <div class="field-error" data-error-for="recurrence_ends_after"></div>
                    </div>
                </div>
            </div>

            {{-- Langkah 2: diisi JS dari hasil rekomendasi. Hanya untuk dilihat, user tidak memilih akun. --}}
            <div class="d-grid gap-3" data-step-show="2" hidden>
                <p class="text-secondary mb-0" data-summary></p>
                <x-alert type="info" title="Akun Zoom ditentukan admin">Daftar di bawah hanya informasi akun yang
                    kosong untuk jadwalmu. Setelah kamu mengajukan, statusnya menjadi menunggu. Admin yang akan
                    memilihkan akun saat menyetujui.</x-alert>
                <div class="d-grid gap-2" data-accounts></div>
                <x-alert type="warning" title="Tidak ada akun yang tersedia" data-no-account hidden>Semua akun penuh
                    pada sebagian tanggal. Kembali dan ubah jam, tanggal, atau tipe akunnya.</x-alert>
            </div>
        </div>

        <div class="offcanvas-foot">
            <div data-step-show="1" class="d-flex gap-2 justify-content-end flex-grow-1">
                <x-button variant="secondary" data-bs-dismiss="offcanvas">Batal</x-button>
                <x-button data-next icon="search">Cek akun tersedia</x-button>
            </div>
            <div data-step-show="2" class="d-flex gap-2 justify-content-end flex-grow-1" hidden>
                <x-button variant="secondary" data-back icon="chevron-left">Ubah jadwal</x-button>
                <x-button type="submit" data-submit icon="check">Ajukan booking</x-button>
            </div>
        </div>
    </form>
</x-offcanvas>