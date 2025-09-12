<?php
namespace Database\Seeders;

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Seeder;

class ServiceRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $service_names = [
            'Electronics',
            'Smartphones',
            'Laptops',
            'Cameras',
            'Headphones',
            'Fashion',
            'Shoes',
            'Accessories',
            'Home & Kitchen',
            'Furniture',
            'Appliances',
            'Cookware',
            'Bedding',
            'Automotive',
            'Motorcycle Accessories',
            'Tools & Hardware',
            'Baby Products',
            'Musical Instruments',
            'Office Supplies',
        ];

        foreach ($service_names as $service_name) {
            ServiceRequest::create([
                'request_by'   => User::where('role', 'PROVIDER')->inRandomOrder()->first()?->id,
                'service_name' => $service_name,
            ]);
        }
    }
}
