<?php
namespace Database\Seeders;

use App\Models\User;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker      = Faker::create();
        $totalUsers = 5;

        for ($i = 1; $i <= $totalUsers; $i++) {
            User::create([
                'name'  => "System User $i",
                'email' => "user{$i}@gmail.com",
                'phone'             => $faker->phoneNumber,
                'address'           => $faker->address,
                'latitude'          => $faker->latitude(20.5, 26.5),
                'longitude'         => $faker->longitude(88.0, 92.0),
                'role'              => 'USER',
                'password'          => Hash::make('1234'),
                'referral_code'     => rand(100000, 999999),
                'email_verified_at' => now(),
                'is_kyc_verified'=>rand(0,1),
                'status'            => 'active',
            ]);
        }








        $totalProvider = 5;

        for ($i = 1; $i <= $totalProvider; $i++) {
            User::create([
                'name'  => "System Provider $i",
                'email' => "provider{$i}@gmail.com",
                'phone'             => $faker->phoneNumber,
                'address'           => $faker->address,
                'referral_code'     => rand(000000, 999999),
                'role'              => 'PROVIDER',
                'email_verified_at' => now(),
                'password'          => Hash::make('1234'),
                'status'            => 'active',
            ]);
        }
    }
}
