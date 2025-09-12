<?php
namespace Database\Seeders;

use App\Models\Service;
use App\Services\FileUploadService;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            'Barbing',
            'Home cleaning',
            'Car wash',
            'Massage',
            'Pedicure',
            'Facials',
            'Manicure',
            'Hair styling',
            'Nail art',
            'Makeup artists',
            'Home cooking',
            'Home fumigation',
        ];

        foreach ($services as $service) {
            $fileUpload = new FileUploadService('public_path', 'placeholders/services');
            $image      = $fileUpload->generatePlaceholderImage();
            Service::create([
                'name'  => $service,
                'image' => $image,
            ]);
        }
    }
}
