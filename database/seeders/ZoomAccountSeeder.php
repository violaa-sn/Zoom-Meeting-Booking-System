<?php

namespace Database\Seeders;

use App\Models\ZoomAccount;
use Illuminate\Database\Seeder;

/**
 * Akun Zoom contoh dengan kapasitas peserta berbeda, supaya filter "jumlah peserta" bisa dicoba:
 * 100 / 200 / 300 / 500 / di atas 500. Ada juga contoh langganan hampir habis, sudah habis, dan akun nonaktif.
 */
class ZoomAccountSeeder extends Seeder
{
    public function run(): void
    {
        $in = fn (int $days) => now()->addDays($days)->toDateString();

        // [nama, email, tipe, login bersamaan, maks. peserta, langganan sampai, status, akun utama, warna]
        $accounts = [
            ['Zoom Utama', 'utama@perusahaan.test', 'business', 2, 300, $in(365), 'active', true, '#3b6ee0'],
            ['Zoom Kelas A', 'kelas-a@perusahaan.test', 'pro', 1, 100, $in(365), 'active', false, '#7c4dff'],
            ['Zoom Menengah', 'menengah@perusahaan.test', 'pro', 1, 200, $in(365), 'active', false, '#d946ef'],
            ['Zoom Kelas B', 'kelas-b@perusahaan.test', 'pro', 1, 300, $in(365), 'active', false, '#1fa971'],
            ['Zoom Cadangan', 'cadangan@perusahaan.test', 'business', 2, 300, $in(365), 'active', false, '#f08a24'],
            ['Zoom Webinar', 'webinar@perusahaan.test', 'business', 1, 500, $in(365), 'active', false, '#e5484d'],
            ['Zoom Besar', 'besar@perusahaan.test', 'business', 1, 1000, $in(365), 'active', false, '#0ea5b7'],
            // Langganan hampir habis (badge kuning di halaman Akun Zoom)
            ['Zoom Sementara', 'sementara@perusahaan.test', 'pro', 1, 100, $in(20), 'active', false, '#a3a847'],
            // Langganan sudah habis
            ['Zoom Kedaluwarsa', 'kedaluwarsa@perusahaan.test', 'pro', 1, 100, $in(-5), 'active', false, '#8b8b8b'],
            // Akun nonaktif
            ['Zoom Nonaktif', 'nonaktif@perusahaan.test', 'business', 1, 300, $in(365), 'inactive', false, '#c2410c'],
        ];

        foreach ($accounts as $i => [$name, $email, $type, $logins, $participants, $expires, $status, $default, $color]) {
            ZoomAccount::firstOrCreate(['email' => $email], [
                'name' => $name,
                'account_type' => $type,
                'allowed_login_users' => $logins,
                'participant_capacity' => $participants,
                'subscription_expires_at' => $expires,
                'status' => $status,
                'is_default' => $default,
                'color' => $color,
                'sort_order' => $i,
            ]);
        }
    }
}
