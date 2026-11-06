<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(['username' => 'admin'], [
            'name' => 'Administrator', 'role' => User::ROLE_ADMIN, 'status' => User::STATUS_ACTIVE, 'password' => 'ganti-password',
        ]);
        User::firstOrCreate(['username' => 'stefan'], [
            'name' => 'Stefan Wijaya', 'department_id' => Department::where('code', 'MIA')->value('id'),
            'role' => User::ROLE_USER, 'status' => User::STATUS_ACTIVE, 'password' => 'ganti-password',
        ]);
    }
}
