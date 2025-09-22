<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class ReferUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $count=100;
        $users = User::pluck('id')->toArray();
        for ($i = 0; $i < $count; $i++) {
            $referrer = $users[array_rand($users)];
            do {
                $referred = $users[array_rand($users)];
            } while ($referrer === $referred);

            DB::table('refer_users')->insert([
                'referrer' => $referrer,
                'referred' => $referred,
                'referral_rewards' => rand(10, 100),
                'status' => collect(['pending', 'approved', 'rejected'])->random(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
