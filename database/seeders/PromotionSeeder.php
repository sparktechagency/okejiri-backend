<?php
namespace Database\Seeders;

use App\Models\Promotion;
use App\Services\FileUploadService;
use Illuminate\Database\Seeder;

class PromotionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $totalPromotions = 5;
        for ($i = 1; $i <= $totalPromotions; $i++) {
            $fileUpload = new FileUploadService('public_path', 'placeholders/promotions');
            $image      = $fileUpload->generatePlaceholderImage();
            Promotion::create([
                'image' => $image,
            ]);
        }
    }
}
