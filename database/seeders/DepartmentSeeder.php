<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

/** Departemen contoh yang bersifat umum. Ubah lewat menu Departemen sesuai struktur sebenarnya. */
class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        // kode (maks. 10 karakter, dipakai sebagai label di kalender) => nama
        $departments = [
            'HRD' => 'Sumber Daya Manusia',
            'FIN' => 'Keuangan',
            'MKT' => 'Pemasaran',
            'OPS' => 'Operasional',
            'ITD' => 'Teknologi Informasi',
            'LGL' => 'Hukum',
            'TRN' => 'Pelatihan',
            'PRC' => 'Pengadaan',
            'PRJ' => 'Manajemen Proyek',
            'CSV' => 'Layanan Pelanggan',
            'GEN' => 'Umum',
        ];

        foreach ($departments as $code => $name) {
            Department::firstOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
