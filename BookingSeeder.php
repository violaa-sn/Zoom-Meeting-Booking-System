<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\User;
use App\Models\ZoomAccount;
use Illuminate\Database\Seeder;

/** Contoh pengajuan menunggu, supaya menu Persetujuan ada isinya. */
class BookingSeeder extends Seeder
{
    public function run(): void
    {
        if (Booking::count() > 0) return;

        $stefan = User::where('username', 'stefan')->first();
        $utama = ZoomAccount::where('email', 'utama@gereja.test')->first();
        $kelasA = ZoomAccount::where('email', 'kelas-a@gereja.test')->first();

        Booking::create([
            'user_id' => $stefan->id, 'zoom_account_id' => $utama->id, 'purpose' => 'rapat', 'participant_count' => 12,
            'starts_on' => now()->addDay()->toDateString(), 'start_time' => '09:00', 'end_time' => '10:00',
            'recurrence_frequency' => Booking::RECURRENCE_NONE, 'status' => Booking::STATUS_PENDING,
        ]);
        Booking::create([
            'user_id' => $stefan->id, 'zoom_account_id' => $kelasA->id, 'purpose' => 'pelatihan', 'participant_count' => 25,
            'starts_on' => now()->addDays(2)->toDateString(), 'start_time' => '19:00', 'end_time' => '21:00',
            'recurrence_frequency' => Booking::RECURRENCE_DAY, 'repeat_interval' => 7,
            'recurrence_end_type' => Booking::END_AFTER, 'recurrence_ends_after' => 4, 'status' => Booking::STATUS_PENDING,
        ]);
    }
}
