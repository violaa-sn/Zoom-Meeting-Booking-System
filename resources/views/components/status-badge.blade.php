{{-- Badge status booking: pending, approved, rejected, cancelled. --}}
@props(['status'])
@php
    $map = [
        'pending' => ['Menunggu', 'amber'],
        'approved' => ['Disetujui', 'blue'],
        'rejected' => ['Ditolak', 'red'],
        'cancelled' => ['Dibatalkan', 'grey'],
    ];
    [$label, $tone] = $map[$status] ?? [$status, 'grey'];
@endphp
<x-badge :tone="$tone" {{ $attributes }}>{{ $label }}</x-badge>
