<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\PackageDetail;
use Faker\Factory as Faker;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PackageDetailSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
      $faker        = Faker::create();
        $totalDetails = 200;
        for ($i = 1; $i <= $totalDetails; $i++) {
            $package_id = Package::inRandomOrder()->first()->id;
            PackageDetail::create([
                'package_id'   => $package_id,
                'item'         =>  $faker->sentence(4),
            ]);
        }
    }
}
