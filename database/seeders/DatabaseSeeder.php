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
            SettingSeeder::class,
            AdminSeeder::class,
            UserSeeder::class,
            ProviderSeeder::class,
            CompanySeeder::class,
            ServiceSeeder::class,
            ProviderServiceSeeder::class,
            PageSeeder::class,
            FaqSeeder::class,
            PromotionSeeder::class,
            PackageSeeder::class,
            ServiceRequestSeeder::class,
        ]);
    }
}
