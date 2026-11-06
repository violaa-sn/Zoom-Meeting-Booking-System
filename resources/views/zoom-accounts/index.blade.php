@php
    $types = ['pro' => 'Pro', 'business' => 'Business'];
    $statuses = ['active' => 'Aktif', 'inactive' => 'Nonaktif'];
    $palette = ['#3b6ee0', '#7c4dff', '#1fa971', '#f08a24', '#e0457b', '#0ea5b7', '#8a6d3b', '#64748b'];
    $editing = old('_edit_id');
@endphp
<x-layouts.app title="Akun Zoom">
    <x-page-header title="Akun Zoom"
        subtitle="Akun yang bisa dipinjam. Status Penuh mengikuti kapasitas meeting tiap akun.">
        <x-button icon="plus" data-bs-toggle="offcanvas" data-bs-target="#account-panel" data-mode="create">Tambah akun
            Zoom</x-button>
    </x-page-header>

    <div class="card overflow-hidden">
        <div class="table-responsive">
            <table class="table table-stack mb-0">
                <thead>
                    <tr>
                        <th>Akun</th>
                        <th>Tipe</th>
                        <th>Kapasitas meeting</th>
                        <th>Maks. peserta</th>
                        <th>Langganan sampai</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($accounts as $a)
                        @php
                            $days = (int) now()->startOfDay()->diffInDays($a->subscription_expires_at, false);
                            $payload = $a->only([
                                'id',
                                'name',
                                'email',
                                'account_type',
                                'allowed_login_users',
                                'participant_capacity',
                                'status',
                                'is_default',
                                'color',
                                'sort_order',
                            ]) + ['subscription_expires_at' => $a->subscription_expires_at->format('Y-m-d')];
                        @endphp
                        <tr>
                            <td class="td-first" data-label="Akun">
                                <div class="d-flex align-items-center gap-2"><span class="acct-dot"
                                        style="--c: {{ $a->color ?: '#3b6ee0' }}"><x-icon name="video"
                                            :size="16" /></span>
                                    <div class="cell-main"><b>{{ $a->name }} @if ($a->is_default)
                                                <x-badge tone="blue">Akun utama</x-badge>
                                            @endif
                                        </b>
                                        <small>{{ $a->email }}</small>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Tipe">{{ $types[$a->account_type] }}</td>
                            <td data-label="Kapasitas meeting">{{ $a->allowed_login_users }}</td>
                            <td data-label="Maks. peserta">{{ number_format($a->participant_capacity, 0, ',', '.') }}
                            </td>
                            <td data-label="Langganan sampai">{{ $a->subscription_expires_at->format('d/m/Y') }}
                                @if ($days < 0)
                                    <x-badge tone="red">Berakhir</x-badge>
                                @elseif ($days <= 30)
                                    <x-badge tone="amber">{{ $days }} hari lagi</x-badge>
                                @endif
                            </td>
                            <td data-label="Status"><x-badge :tone="$a->status === 'active' ? 'green' : 'grey'">{{ $statuses[$a->status] }}</x-badge>
                            </td>
                            <td data-label="Aksi">
                                <div class="d-flex justify-content-end gap-1">
                                    <button type="button" class="btn btn-light btn-icon" title="Ubah"
                                        aria-label="Ubah {{ $a->name }}" data-bs-toggle="offcanvas"
                                        data-bs-target="#account-panel" data-mode="edit"
                                        data-action="{{ route('zoom-accounts.update', $a) }}"
                                        data-values="{{ json_encode($payload) }}"><x-icon name="pencil"
                                            :size="17" /></button>
                                    <x-delete-form :action="route('zoom-accounts.destroy', $a)" title="Hapus {{ $a->name }}?"
                                        text="Akun yang masih punya booking pending atau meeting mendatang tidak bisa dihapus."
                                        label="Hapus akun" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" data-label="">
                                <div class="p-4 text-center text-secondary">Belum ada akun Zoom. Tambahkan satu supaya
                                    kalender bisa dipakai.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-slot:extra>
        <x-offcanvas id="account-panel" title="{{ $editing ? 'Ubah akun Zoom' : 'Tambah akun Zoom' }}" data-resource
            data-title-create="Tambah akun Zoom" data-title-edit="Ubah akun Zoom" data-submit-create="Simpan akun"
            :open="old('_form') === 'account-form'">
            <form class="offcanvas-form" method="POST"
                action="{{ $editing ? route('zoom-accounts.update', $editing) : route('zoom-accounts.store') }}"
                data-create-action="{{ route('zoom-accounts.store') }}">
                @csrf
                <input type="hidden" name="_method" value="PUT" data-method-input @disabled(!$editing)>
                <input type="hidden" name="_form" value="account-form">
                <input type="hidden" name="_edit_id" value="{{ $editing }}">
                <div class="offcanvas-body d-grid gap-3 align-content-start">
                    <x-input label="Nama akun" name="name" placeholder="Zoom Utama" required />
                    <x-input label="Email akun Zoom" name="email" type="email" required />
                    <div class="row g-3">
                        <x-select wrapperClass="col-sm-6" label="Tipe akun" name="account_type" :options="$types"
                            value="pro" />
                        <x-input wrapperClass="col-sm-6" label="Langganan sampai" name="subscription_expires_at"
                            type="date" required />
                        <x-input wrapperClass="col-sm-6" label="Kapasitas meeting" name="allowed_login_users"
                            type="number" min="1" value="1"
                            hint="Meeting yang boleh berjalan bersamaan. Patokan status Penuh." required />
                        <x-input wrapperClass="col-sm-6" label="Maks. peserta" name="participant_capacity"
                            type="number" min="1" value="100"
                            hint="Hanya informasi, tidak memblokir booking." required />
                    </div>
                    <div>
                        <span class="form-label d-block">Warna di kalender</span>
                        <div class="swatches">
                            @foreach ($palette as $i => $hex)
                                <label class="swatch" style="--c: {{ $hex }}"><input type="radio"
                                        name="color" value="{{ $hex }}" @checked(old('color', $palette[0]) === $hex)
                                        aria-label="Warna {{ $i + 1 }}"><span></span></label>
                            @endforeach
                        </div>
                        <div class="field-error" data-error-for="color">
                            @error('color')
                                {{ $message }}
                            @enderror
                        </div>
                    </div>
                    <div class="row g-3">
                        <x-select wrapperClass="col-sm-6" label="Status" name="status" :options="$statuses"
                            value="active" />
                        <x-input wrapperClass="col-sm-6" label="Urutan tampil" name="sort_order" type="number"
                            min="0" value="0" />
                    </div>
                    <x-checkbox label="Jadikan akun utama" name="is_default"
                        hint="Akun utama tampil pertama di kalender dan paling atas di rekomendasi." />
                </div>
                <div class="offcanvas-foot">
                    <x-button variant="secondary" data-bs-dismiss="offcanvas">Batal</x-button>
                    <x-button type="submit" icon="check"><span
                            data-submit-label>{{ $editing ? 'Simpan perubahan' : 'Simpan akun' }}</span></x-button>
                </div>
            </form>
        </x-offcanvas>
    </x-slot:extra>
</x-layouts.app>
