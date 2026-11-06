<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['MIA', 'MHW', 'KTB', 'PIC', 'MIC'] as $code) {
            Department::firstOrCreate(['code' => $code], ['name' => "Departemen {$code}"]);
        }
    }
}
