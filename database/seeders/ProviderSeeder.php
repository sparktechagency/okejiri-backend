<?php
namespace Database\Seeders;

use App\Models\User as PROVIDER;
use App\Services\FileUploadService;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ProviderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker         = Faker::create();
        $statuses      = ['Unverified', 'In Review', 'Verified','Rejected'];
        $provider_type = ['Individual', 'Company'];
        $totalProvider = 5;
        for ($i = 1; $i <= $totalProvider; $i++) {
            $fileUpload    = new FileUploadService('public_path');
            $id_card_front = $fileUpload->setPath('placeholders/users/kyc/id_card_front')->generatePlaceholderImage();
            $id_card_back  = $fileUpload->setPath('placeholders/users/kyc/id_card_back')->generatePlaceholderImage();
            $selfie        = $fileUpload->setPath('placeholders/users/kyc/selfie')->generatePlaceholderImage(512, 512);
            PROVIDER::create([
                'name'  => "System Provider $i",
                'email' => "provider{$i}@gmail.com",
                'referral_code'               => rand(000000, 999999),
                'phone'                       => $faker->phoneNumber,
                'address'                     => $faker->address,
                'role'                        => 'PROVIDER',
                'email_verified_at'           => now(),
                'provider_type'               => $provider_type[array_rand($provider_type)],
                'about'                       => $faker->paragraph(),
                'latitude'                    => $faker->latitude(20.5, 26.5),
                'longitude'                   => $faker->longitude(88.0, 92.0),
                'password'                    => Hash::make('1234'),
                'status'                      => 'active',
                'kyc_status'                  => $statuses[array_rand($statuses)],
                'id_card_front'               => $id_card_front,
                'id_card_back'                => $id_card_back,
                'selfie'                      => $selfie,
                'has_service'=>true,
                'discount'=>rand(0, 50),
                'is_personalization_complete' => rand(0, 1),
            ]);
        }
    }
}
