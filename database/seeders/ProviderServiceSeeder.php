<?php
namespace Database\Seeders;

use App\Models\ProviderService;
use App\Models\Service;
use App\Models\User as PROVIDER;
use Illuminate\Database\Seeder;

class ProviderServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $providers = PROVIDER::where('role', 'PROVIDER')->get();
        $services  = Service::all();
        foreach ($providers as $provider) {
            if ($provider->provider_type === 'Company') {
                $randomServices = $services->random(rand(2, 4));
                foreach ($randomServices as $service) {
                    ProviderService::create([
                        'provider_id' => $provider->id,
                        'service_id'  => $service->id,
                    ]);
                }
            }
            elseif($provider->provider_type === 'Individual') {
                $service = $services->random();
                ProviderService::create([
                    'provider_id' => $provider->id,
                    'service_id'  => $service->id,
                ]);
            }
        }
    }
}
