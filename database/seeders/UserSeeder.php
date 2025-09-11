<?php
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;
class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();
        $totalUsers = 5;

        for ($i = 1; $i <= $totalUsers; $i++) {
            User::create([
                'name' => "System User $i",
                'email' => "user{$i}@gmail.com",
                'phone' => $faker->phoneNumber,
                'address' => $faker->address,
                'role' => 'USER',
                'email_verified_at' => now(),
                'password' => Hash::make('1234'),
                'status' => 'active',
            ]);
        }
    }
}
