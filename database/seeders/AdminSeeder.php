<?php

namespace Database\Seeders;

use App\Models\User as Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Admin::create([
            'name' => 'System Admin',
            'email' => 'admin@gmail.com',
            'phone' => '706-501-4280',
            'address' => '123 Faketown Rd, Nowhere, ZZ',
            'role' => 'ADMIN',
            'email_verified_at' => now(),
            'password' => Hash::make('1234'),
            'status' => 'active',
            'is_personalization_complete'=>true,
        ]);
    }
}
