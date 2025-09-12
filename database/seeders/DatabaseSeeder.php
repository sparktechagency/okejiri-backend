<?php
namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\ReferedUser;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            UserSeeder::class,
            PageSeeder::class,
            FaqSeeder::class,
            PromotionSeeder::class,
            ServiceSeeder::class,
            PackageSeeder::class,
            ServiceRequestSeeder::class,


            // ReferredUserSeeder::class,
        ]);
    }
}
