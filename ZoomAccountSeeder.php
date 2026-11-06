<?php

namespace Database\Seeders;

use App\Models\ZoomAccount;
use Illuminate\Database\Seeder;

class ZoomAccountSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['Zoom Utama', 'utama@gereja.test', 2, '#3b6ee0', true],
            ['Zoom Kelas A', 'kelas-a@gereja.test', 1, '#7c4dff', false],
            ['Zoom Kelas B', 'kelas-b@gereja.test', 1, '#1fa971', false],
            ['Zoom Cadangan', 'cadangan@gereja.test', 2, '#f08a24', false],
        ] as $i => [$name, $email, $capacity, $color, $default]) {
            ZoomAccount::firstOrCreate(['email' => $email], [
                'name' => $name, 'account_type' => $capacity > 1 ? 'business' : 'pro', 'allowed_login_users' => $capacity,
                'participant_capacity' => 100, 'subscription_expires_at' => now()->addYear()->toDateString(),
                'status' => 'active', 'is_default' => $default, 'color' => $color, 'sort_order' => $i,
            ]);
        }
    }
}
