<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Urutan penting: departemen -> user -> akun Zoom -> booking. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DepartmentSeeder::class,
            UserSeeder::class,
            ZoomAccountSeeder::class,
            BookingSeeder::class,
        ]);
    }
}
