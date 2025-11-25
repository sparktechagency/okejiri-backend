<?php
namespace Database\Seeders;

use App\Models\User;
use App\Services\FileUploadService;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker      = Faker::create();
        $statuses   = ['Unverified', 'In Review', 'Verified', 'Rejected'];
        $totalUsers = 5;

        for ($i = 1; $i <= $totalUsers; $i++) {
            $fileUpload    = new FileUploadService('public_path');
            $id_card_front = $fileUpload->setPath('placeholders/users/kyc/id_card_front')->generatePlaceholderImage();
            $id_card_back  = $fileUpload->setPath('placeholders/users/kyc/id_card_back')->generatePlaceholderImage();
            $selfie        = $fileUpload->setPath('placeholders/users/kyc/selfie')->generatePlaceholderImage(512, 512);
            $walletAddress = 'ACC-' . substr(md5(uniqid()), 0, 20);
            User::create([
                'name'  => "System User $i",
                'email' => "user{$i}@gmail.com",
                'phone'                       => $faker->phoneNumber,
                'address'                     => $faker->address,
                'latitude'                    => $faker->latitude(20.5, 26.5),
                'longitude'                   => $faker->longitude(88.0, 92.0),
                'role'                        => 'USER',
                'password'                    => Hash::make('1234'),
                'referral_code'               => rand(100000, 999999),
                'email_verified_at'           => now(),
                'status'                      => 'active',
                'kyc_status'                  => $statuses[array_rand($statuses)],
                'id_card_front'               => $id_card_front,
                'id_card_back'                => $id_card_back,
                'selfie'                      => $selfie,
                'is_personalization_complete' => rand(0, 1),
                'wallet_address'              => $walletAddress,
            ]);
        }
    }
}
