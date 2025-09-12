<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $totalPackage = 500;
        for ($i = 1; $i <= $totalPackage; $i++) {
            Package::create([
                'service_id'=>rand(1,12),
                'title'=>'a'
            ]);
        }
    }
}
