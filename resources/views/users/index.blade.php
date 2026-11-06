@php
    $roles = ['user' => 'Pengguna', 'admin' => 'Admin'];
    $statuses = ['active' => 'Aktif', 'inactive' => 'Nonaktif'];
    $deptOptions = $departments->pluck('name', 'id')->all();
    $editing = old('_edit_id');
@endphp
<x-layouts.app title="Pengguna">
    <x-page-header title="Pengguna" subtitle="Kelola siapa yang boleh login dan meminjam akun Zoom.">
        <x-button icon="plus" data-bs-toggle="offcanvas" data-bs-target="#user-panel" data-mode="create">Tambah
            pengguna</x-button>
    </x-page-header>

    <form method="GET" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <x-filter-input wrapperClass="col-12 col-md-4 col-xl-3" label="Cari" name="search" :value="request('search')"
                placeholder="Nama atau username" />
            <x-filter-select wrapperClass="col-6 col-md-4 col-xl-2" label="Departemen" name="dept" :value="request('dept')"
                placeholder="Semua" :options="$deptOptions" />
            <x-filter-select wrapperClass="col-6 col-md-2 col-xl-2" label="Peran" name="role" :value="request('role')"
                placeholder="Semua" :options="$roles" />
            <x-filter-select wrapperClass="col-6 col-md-2 col-xl-2" label="Status" name="status" :value="request('status')"
                placeholder="Semua" :options="$statuses" />
            <div class="col-12 col-xl-auto d-flex gap-2 pb-3">
                <x-button type="submit" variant="secondary" icon="search" class="flex-fill">Terapkan</x-button>
                <x-button href="{{ route('users.index') }}" variant="ghost">Atur ulang</x-button>
            </div>
        </div>
    </form>

    <div class="card overflow-hidden">
        <div class="table-responsive">
            <table class="table table-stack mb-0">
                <thead>
                    <tr>
                        <th>Pengguna</th>
                        <th>Departemen</th>
                        <th>Peran</th>
                        <th>Status</th>
                        <th>Booking</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $u)
                        <tr>
                            <td class="td-first" data-label="Pengguna">
                                <div class="d-flex align-items-center gap-2"><span
                                        class="avatar avatar--soft">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($u->name, 0, 1)) }}</span>
                                    <div class="cell-main">
                                        <b>{{ $u->name }}</b><small>{{ '@' . $u->username }}</small>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Departemen">{{ $u->department?->name }}</td>
                            <td data-label="Peran"><x-badge :tone="$u->role === 'admin' ? 'blue' : 'grey'">{{ $roles[$u->role] }}</x-badge></td>
                            <td data-label="Status"><x-badge :tone="$u->status === 'active' ? 'green' : 'red'">{{ $statuses[$u->status] }}</x-badge>
                            </td>
                            <td data-label="Booking">{{ $u->bookings_count }}</td>
                            <td data-label="Aksi">
                                <div class="d-flex justify-content-end gap-1">
                                    <button type="button" class="btn btn-light btn-icon" title="Ubah"
                                        aria-label="Ubah {{ $u->name }}" data-bs-toggle="offcanvas"
                                        data-bs-target="#user-panel" data-mode="edit"
                                        data-action="{{ route('users.update', $u) }}"
                                        data-values="{{ json_encode(['id' => $u->id, 'name' => $u->name, 'username' => $u->username, 'department_id' => $u->department_id, 'role' => $u->role, 'status' => $u->status]) }}"><x-icon
                                            name="pencil" :size="17" /></button>
                                    @if ($u->id !== auth()->id())
                                        <x-delete-form :action="route('users.destroy', $u)" title="Hapus {{ $u->name }}?"
                                            text="Pengguna yang pernah booking tidak bisa dihapus. Nonaktifkan saja."
                                            label="Hapus pengguna" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" data-label="">
                                <div class="p-4 text-center text-secondary">Belum ada pengguna yang cocok.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())
            <div class="p-3 border-top">{{ $users->links() }}</div>
        @endif
    </div>

    <x-slot:extra>
        <x-offcanvas id="user-panel" title="{{ $editing ? 'Ubah pengguna' : 'Tambah pengguna' }}" data-resource
            data-title-create="Tambah pengguna" data-title-edit="Ubah pengguna" data-submit-create="Simpan pengguna"
            :open="old('_form') === 'user-form'">
            <form class="offcanvas-form" method="POST"
                action="{{ $editing ? route('users.update', $editing) : route('users.store') }}"
                data-create-action="{{ route('users.store') }}">
                @csrf
                <input type="hidden" name="_method" value="PUT" data-method-input @disabled(!$editing)>
                <input type="hidden" name="_form" value="user-form">
                <input type="hidden" name="_edit_id" value="{{ $editing }}">
                <div class="offcanvas-body d-grid gap-3 align-content-start">
                    <x-input label="Nama lengkap" name="name" required />
                    <x-input label="Username" name="username"
                        hint="Dipakai untuk login. Huruf, angka, strip, titik, atau garis bawah." autocomplete="off"
                        required />
                    <div class="row g-3">
                        <x-select wrapperClass="col-sm-6" label="Departemen" name="department_id" :options="$deptOptions"
                            placeholder="Pilih departemen" :required="true" />
                        <x-select wrapperClass="col-sm-6" label="Peran" name="role" :options="$roles"
                            value="user" />
                    </div>
                    <x-select label="Status" name="status" :options="$statuses" value="active"
                        hint="Pengguna nonaktif tidak bisa login, riwayat bookingnya tetap ada." />
                    <x-input label="Password" name="password" type="password" autocomplete="new-password"
                        data-create-required :required="!$editing"
                        hint="Minimal 8 karakter. Saat mengubah pengguna, kosongkan jika password tidak diganti." />
                </div>
                <div class="offcanvas-foot">
                    <x-button variant="secondary" data-bs-dismiss="offcanvas">Batal</x-button>
                    <x-button type="submit" icon="check"><span
                            data-submit-label>{{ $editing ? 'Simpan perubahan' : 'Simpan pengguna' }}</span></x-button>
                </div>
            </form>
        </x-offcanvas>
    </x-slot:extra>
</x-layouts.app>
