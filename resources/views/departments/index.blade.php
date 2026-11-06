@php $editing = old('_edit_id'); @endphp
<x-layouts.app title="Departemen">
    <x-page-header title="Departemen" subtitle="Kode departemen tampil sebagai label di kalender, mis. MIA atau KTB.">
        <x-button icon="plus" data-bs-toggle="offcanvas" data-bs-target="#department-panel" data-mode="create">Tambah departemen</x-button>
    </x-page-header>

    <div class="card overflow-hidden">
        <div class="table-responsive">
            <table class="table table-stack mb-0">
                <thead><tr><th>Departemen</th><th>Kode</th><th>Pengguna</th><th></th></tr></thead>
                <tbody>
                @forelse ($departments as $d)
                    <tr>
                        <td class="td-first" data-label="Departemen"><b>{{ $d->name }}</b></td>
                        <td data-label="Kode"><x-badge tone="blue">{{ $d->code }}</x-badge></td>
                        <td data-label="Pengguna">{{ $d->users_count }}</td>
                        <td data-label="Aksi">
                            <div class="d-flex justify-content-end gap-1">
                                <button type="button" class="btn btn-light btn-icon" title="Ubah" aria-label="Ubah {{ $d->name }}"
                                    data-bs-toggle="offcanvas" data-bs-target="#department-panel" data-mode="edit" data-action="{{ route('departments.update', $d) }}"
                                    data-values="{{ json_encode(['id' => $d->id, 'name' => $d->name, 'code' => $d->code]) }}"><x-icon name="pencil" :size="17" /></button>
                                <x-delete-form :action="route('departments.destroy', $d)" title="Hapus {{ $d->name }}?" text="Departemen yang masih punya pengguna tidak bisa dihapus." label="Hapus departemen" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" data-label=""><div class="p-4 text-center text-secondary">Belum ada departemen. Tambahkan satu supaya bisa dipilih di data pengguna.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-slot:extra>
        <x-offcanvas id="department-panel" title="{{ $editing ? 'Ubah departemen' : 'Tambah departemen' }}" data-resource data-title-create="Tambah departemen" data-title-edit="Ubah departemen" data-submit-create="Simpan departemen" :open="old('_form') === 'department-form'">
            <form class="offcanvas-form" method="POST" action="{{ $editing ? route('departments.update', $editing) : route('departments.store') }}" data-create-action="{{ route('departments.store') }}">
                @csrf
                <input type="hidden" name="_method" value="PUT" data-method-input @disabled(! $editing)>
                <input type="hidden" name="_form" value="department-form">
                <input type="hidden" name="_edit_id" value="{{ $editing }}">
                <div class="offcanvas-body d-grid gap-3 align-content-start">
                    <x-input label="Nama departemen" name="name" maxlength="255" required />
                    <x-input label="Kode" name="code" maxlength="10" style="text-transform:uppercase" autocomplete="off" required
                        hint="Inisial untuk label di kalender, maksimal 10 huruf atau angka. Contoh: MIA." />
                </div>
                <div class="offcanvas-foot">
                    <x-button variant="secondary" data-bs-dismiss="offcanvas">Batal</x-button>
                    <x-button type="submit" icon="check"><span data-submit-label>{{ $editing ? 'Simpan perubahan' : 'Simpan departemen' }}</span></x-button>
                </div>
            </form>
        </x-offcanvas>
    </x-slot:extra>
</x-layouts.app>
