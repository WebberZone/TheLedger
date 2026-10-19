<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Single-user app: one seeded account, no registration flow.
        // Set LEDGER_USER_PASSWORD in .env before seeding in production.
        User::firstOrCreate(
            ['email' => config('ledger.user.email')],
            [
                'name' => config('ledger.user.name'),
                'password' => Hash::make(config('ledger.user.password')),
            ]
        );

        $this->call(LookupSeeder::class);
    }
}
