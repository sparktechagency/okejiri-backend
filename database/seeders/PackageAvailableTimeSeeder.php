<?php
namespace Database\Seeders;

use App\Models\Package;
use App\Models\PackageAvailableTime;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class PackageAvailableTimeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $totalTime = 80;
        $faker     = Faker::create();
        for ($i = 1; $i <= $totalTime; $i++) {
            $package_id = Package::inRandomOrder()->first()->id;
            PackageAvailableTime::create([
                'package_id'          => $package_id,
                'available_time_from' => $faker->time('h:i A'),
                'available_time_to'   => $faker->time('h:i A'),
            ]);
        }
    }
}
