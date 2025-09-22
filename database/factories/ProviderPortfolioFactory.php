<?php
namespace Database\Factories;

use App\Models\User as Provider;
use App\Services\FileUploadService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProviderPortfolio>
 */
class ProviderPortfolioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $provider   = Provider::where('role', 'PROVIDER')->inRandomOrder()->first();
        $fileUpload = new FileUploadService('public_path');
        $image      = $fileUpload->setPath('placeholders/portfolio')->generatePlaceholderImage();
        return [
            'provider_id' => $provider->id,
            'image'       => $image,
        ];
    }
}
