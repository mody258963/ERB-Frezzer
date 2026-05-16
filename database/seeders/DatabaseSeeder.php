<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Branch::query()->create([
            'name' => 'Main Branch',
            'address' => 'Cairo',
            'phone' => '0100000000',
            'is_active' => true,
        ]);

        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => UserRole::Admin,
            'branch_id' => null,
            'is_active' => true,
        ]);
    }
}
