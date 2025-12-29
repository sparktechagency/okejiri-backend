<?php
namespace Database\Factories;

use App\Models\Service;
use App\Models\User;
use App\Services\FileUploadService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fileUpload = new FileUploadService('public_path');
        $image      = $fileUpload->setPath('placeholders/employee/')->generatePlaceholderImage();
        return [
            'provider_id' => User::where('role', 'PROVIDER')->where('provider_type', 'Company')->inRandomOrder()->first()?->id,
            // 'service_id'  => Service::inRandomOrder()->first()?->id,
            'image'       => $image,
            'name'        => $this->faker->name(),
            'phone'       => $this->faker->phoneNumber(),
            'location'    => $this->faker->address(),
        ];
    }
}
