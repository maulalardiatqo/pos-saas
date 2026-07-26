<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            [
                'email' => 'admin@platform.com',
            ],
            [
                'company_id' => null,
                'outlet_id' => null,
                'role_id' => null,

                'user_type' => 'platform',

                'name' => 'Super Administrator',

                'password' => 'default123',

                'email_verified_at' => now(),
            ]
        );
    }
}