<?php

namespace Database\Seeders;

use App\Models\ProviderPortfolio;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProviderPortfolioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
       ProviderPortfolio::factory()->count(30)->create();
    }
}
