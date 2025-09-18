<?php
namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use App\Services\FileUploadService;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = User::where('role', 'PROVIDER')->where('provider_type', 'Company')->get();
        foreach ($companies as $company) {
            $fileUpload = new FileUploadService('public_path');
            $logo       = $fileUpload->setPath('placeholders/companies')->generatePlaceholderImage(512, 512);
            Company::create([
                'provider_id'      => $company->id,
                'company_logo'     => $logo,
                'company_name'     => fake()->company(),
                'company_location' => fake()->address(),
                'company_about'    => fake()->sentence(20),
                'emp_no'           => fake()->numberBetween(10, 20),
            ]);
        }

    }
}
