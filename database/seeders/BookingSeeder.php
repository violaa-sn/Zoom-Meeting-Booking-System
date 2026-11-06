<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingNotification;
use App\Models\BookingOccurrence;
use App\Models\User;
use App\Models\ZoomAccount;
use App\Services\BookingApprovalService;
use Illuminate\Database\Seeder;

/**
 * Contoh pengajuan untuk semua status: menunggu, disetujui (tunggal dan berulang), ditolak, dan dibatalkan.
 * Yang disetujui lewat BookingApprovalService, jadi aturan tipe / kapasitas peserta / bentrok ikut berlaku.
 */
class BookingSeeder extends Seeder
{
    public function run(): void
    {
        if (Booking::count() > 0) {
            return;
        }

        $admin = User::where('username', 'admin')->firstOrFail();
        $approvals = app(BookingApprovalService::class);
        $user = fn (string $u) => User::where('username', $u)->firstOrFail();
        $account = fn (string $email) => ZoomAccount::where('email', $email)->firstOrFail();
        $day = fn (int $n) => now()->addDays($n)->toDateString();

        $make = fn (string $username, array $attrs) => Booking::create($attrs + [
            'user_id' => $user($username)->id,
            'recurrence_frequency' => Booking::RECURRENCE_NONE,
            'repeat_interval' => 1,
            'status' => Booking::STATUS_PENDING,
        ]);

        // ---- Disetujui ----
        // Rapat besar mingguan, 500 peserta -> Zoom Webinar (kapasitas peserta 500)
        $b = $make('fajar', [
            'account_type' => 'business', 'purpose' => 'lainnya', 'purpose_other' => 'Rapat besar mingguan',
            'participant_count' => '500', 'starts_on' => $day(1), 'start_time' => '09:00', 'end_time' => '11:00',
            'recurrence_frequency' => Booking::RECURRENCE_WEEK, 'recurrence_end_type' => Booking::END_AFTER,
            'recurrence_ends_after' => 8,
        ]);
        $b->days()->create(['day_of_week' => (int) now()->addDay()->dayOfWeek]);
        $approvals->approve($b, $admin, $account('webinar@perusahaan.test')->id);

        // Kelas kecil, 100 peserta -> Zoom Kelas A
        $b = $make('budi', [
            'account_type' => 'pro', 'purpose' => 'pelatihan', 'participant_count' => '100',
            'starts_on' => $day(2), 'start_time' => '19:00', 'end_time' => '21:00',
        ]);
        $approvals->approve($b, $admin, $account('kelas-a@perusahaan.test')->id);

        // Rapat sekali jalan, 100 peserta -> Zoom Utama
        $b = $make('citra', [
            'account_type' => 'business', 'purpose' => 'rapat', 'participant_count' => '100',
            'starts_on' => $day(3), 'start_time' => '10:00', 'end_time' => '11:30',
        ]);
        $approvals->approve($b, $admin, $account('utama@perusahaan.test')->id);

        // Rapat di jam yang sama di akun Utama (kapasitas 2 meeting bersamaan -> masih muat)
        $b = $make('dewi', [
            'account_type' => 'business', 'purpose' => 'diskusi', 'participant_count' => '100',
            'starts_on' => $day(3), 'start_time' => '10:30', 'end_time' => '12:00',
        ]);
        $approvals->approve($b, $admin, $account('utama@perusahaan.test')->id);

        // Presentasi besar, lebih dari 500 peserta -> Zoom Besar
        $b = $make('gita', [
            'account_type' => 'business', 'purpose' => 'presentasi', 'participant_count' => '500+',
            'starts_on' => $day(5), 'start_time' => '13:00', 'end_time' => '16:00',
        ]);
        $approvals->approve($b, $admin, $account('besar@perusahaan.test')->id);

        // ---- Menunggu persetujuan (untuk menu Persetujuan) ----
        $make('andi', [
            'account_type' => 'business', 'purpose' => 'rapat', 'participant_count' => '100',
            'starts_on' => $day(1), 'start_time' => '09:00', 'end_time' => '10:12', // menit bebas
        ]);
        $make('eko', [
            'account_type' => 'pro', 'purpose' => 'pelatihan', 'participant_count' => '300',
            'starts_on' => $day(2), 'start_time' => '19:00', 'end_time' => '21:00',
            'recurrence_frequency' => Booking::RECURRENCE_DAY, 'repeat_interval' => 7,
            'recurrence_end_type' => Booking::END_AFTER, 'recurrence_ends_after' => 4,
        ]);
        $make('hendra', [
            'account_type' => 'pro', 'purpose' => 'lainnya', 'purpose_other' => 'Kelas pelatihan rutin',
            'participant_count' => '100', 'starts_on' => $day(4), 'start_time' => '08:00', 'end_time' => '09:00',
            'recurrence_frequency' => Booking::RECURRENCE_WEEK, 'recurrence_end_type' => Booking::END_ON,
            'recurrence_ends_on' => $day(60),
        ])->days()->create(['day_of_week' => (int) now()->addDays(4)->dayOfWeek]);
        $make('indah', [
            'account_type' => 'business', 'purpose' => 'wawancara', 'participant_count' => '500',
            'starts_on' => $day(6), 'start_time' => '14:00', 'end_time' => '15:00',
        ]);
        $make('joko', [
            'account_type' => 'business', 'purpose' => 'presentasi', 'participant_count' => '500+',
            'starts_on' => $day(7), 'start_time' => '18:30', 'end_time' => '21:00',
        ]);
        $make('kartika', [
            'account_type' => 'pro', 'purpose' => 'diskusi', 'participant_count' => '300',
            'starts_on' => $day(8), 'start_time' => '20:00', 'end_time' => '21:30',
        ]);

        // ---- Ditolak ----
        $b = $make('lina', [
            'account_type' => 'pro', 'purpose' => 'rapat', 'participant_count' => '100',
            'starts_on' => $day(9), 'start_time' => '07:00', 'end_time' => '08:00',
        ]);
        $approvals->reject($b, $admin, 'Jadwal bentrok dengan kegiatan lain, silakan ajukan di hari lain.');

        // ---- Dibatalkan oleh pengguna (booking yang sempat disetujui, slot sudah dikosongkan) ----
        $b = $make('budi', [
            'account_type' => 'pro', 'purpose' => 'rapat', 'participant_count' => '300',
            'starts_on' => $day(10), 'start_time' => '16:00', 'end_time' => '17:00',
        ]);
        $approvals->approve($b, $admin, $account('kelas-b@perusahaan.test')->id);
        BookingOccurrence::where('booking_id', $b->id)->update(['status' => BookingOccurrence::STATUS_CANCELLED]);
        $b->update(['status' => Booking::STATUS_CANCELLED, 'cancelled_at' => now()]);
        BookingNotification::create([
            'user_id' => $admin->id, 'booking_id' => $b->id, 'type' => BookingNotification::TYPE_CANCELLED,
            'message' => 'Budi Santoso membatalkan booking Zoom Kelas B.',
        ]);
    }
}
