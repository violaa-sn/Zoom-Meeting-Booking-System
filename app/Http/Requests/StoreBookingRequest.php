<?php

namespace App\Http\Requests;

/**
 * Pengajuan booking: aturannya sama persis dengan langkah rekomendasi.
 * User TIDAK memilih akun Zoom; akun ditentukan admin saat menyetujui.
 */
class StoreBookingRequest extends RecommendBookingRequest
{
}
