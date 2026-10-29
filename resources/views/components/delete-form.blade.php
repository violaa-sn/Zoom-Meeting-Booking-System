{{-- Tombol hapus dengan konfirmasi SweetAlert. --}}
@props(['action', 'title' => 'Hapus data ini?', 'text' => 'Tindakan ini tidak bisa dibatalkan.', 'button' => 'Ya, hapus', 'label' => 'Hapus'])
<form method="POST" action="{{ $action }}" class="d-inline" data-confirm data-confirm-title="{{ $title }}" data-confirm-text="{{ $text }}" data-confirm-button="{{ $button }}">
    @csrf @method('DELETE')
    <button type="submit" class="btn btn-light btn-icon text-danger" title="{{ $label }}" aria-label="{{ $label }}"><x-icon name="trash" :size="17" /></button>
</form>
