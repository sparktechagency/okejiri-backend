<?php
namespace Database\Seeders;

use App\Models\Package;
use App\Models\Service;
use App\Models\User;
use App\Services\FileUploadService;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker        = Faker::create();
        $totalPackage = 30;
        for ($i = 1; $i <= $totalPackage; $i++) {
            $provider_id = User::where('role', 'PROVIDER')->inRandomOrder()->first()->id;
            $service_id  = Service::inRandomOrder()->first()->id;
            $fileUpload  = new FileUploadService('public_path');
            $image       = $fileUpload->setPath('placeholders/packages')->generatePlaceholderImage();
            Package::create([
                'provider_id'   => $provider_id,
                'service_id'    => $service_id,
                'title'         => $faker->sentence(4),
                'image'         => $image,
                'price'         => rand(50, 500),
                'delivery_time' => rand(1, 10),
            ]);
        }
    }
}
