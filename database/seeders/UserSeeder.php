<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Login memakai username. Semua password contoh: 1234567890 (ganti di produksi!).
 * Setiap pengguna wajib punya departemen.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $dept = fn (string $code) => Department::where('code', $code)->value('id');

        // [username, nama, kode departemen, peran, status]
        $users = [
            ['admin', 'Administrator', 'ITD', User::ROLE_ADMIN, User::STATUS_ACTIVE],
            ['admin2', 'Rina Hartono', 'GEN', User::ROLE_ADMIN, User::STATUS_ACTIVE],

            ['andi', 'Andi Wijaya', 'HRD', User::ROLE_USER, User::STATUS_ACTIVE],
            ['budi', 'Budi Santoso', 'FIN', User::ROLE_USER, User::STATUS_ACTIVE],
            ['citra', 'Citra Tanuwijaya', 'MKT', User::ROLE_USER, User::STATUS_ACTIVE],
            ['dewi', 'Dewi Pranoto', 'OPS', User::ROLE_USER, User::STATUS_ACTIVE],
            ['eko', 'Eko Lestari', 'ITD', User::ROLE_USER, User::STATUS_ACTIVE],
            ['fajar', 'Fajar Gunawan', 'TRN', User::ROLE_USER, User::STATUS_ACTIVE],
            ['gita', 'Gita Kurniawan', 'PRJ', User::ROLE_USER, User::STATUS_ACTIVE],
            ['hendra', 'Hendra Setiawan', 'PRC', User::ROLE_USER, User::STATUS_ACTIVE],
            ['indah', 'Indah Susanto', 'CSV', User::ROLE_USER, User::STATUS_ACTIVE],
            ['joko', 'Joko Halim', 'LGL', User::ROLE_USER, User::STATUS_ACTIVE],
            ['kartika', 'Kartika Salim', 'TRN', User::ROLE_USER, User::STATUS_ACTIVE],
            ['lina', 'Lina Margareta', 'MKT', User::ROLE_USER, User::STATUS_ACTIVE],
            // Contoh pengguna nonaktif (tidak bisa login, riwayat tetap ada)
            ['mantan', 'Pengguna Nonaktif', 'GEN', User::ROLE_USER, User::STATUS_INACTIVE],
        ];

        foreach ($users as [$username, $name, $code, $role, $status]) {
            User::firstOrCreate(['username' => $username], [
                'department_id' => $dept($code),
                'name' => $name,
                'role' => $role,
                'status' => $status,
                'password' => '1234567890',
            ]);
        }
    }
}
