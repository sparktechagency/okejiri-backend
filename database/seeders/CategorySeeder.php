<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Electronics',
            'Smartphones',
            'Laptops',
            'Cameras',
            'Headphones',
            'Fashion',
            'Men\'s Clothing',
            'Women\'s Clothing',
            'Shoes',
            'Accessories',
            'Home & Kitchen',
            'Furniture',
            'Appliances',
            'Cookware',
            'Bedding',
            'Beauty & Personal Care',
            'Health & Wellness',
            'Books',
            'Stationery',
            'Sports & Outdoors',
            'Fitness Equipment',
            'Toys & Games',
            'Video Games',
            'Gaming Consoles',
            'Grocery',
            'Snacks',
            'Beverages',
            'Pet Supplies',
            'Automotive',
            'Motorcycle Accessories',
            'Tools & Hardware',
            'Baby Products',
            'Musical Instruments',
            'Office Supplies',
        ];

        foreach ($categories as $categoryName) {
            Category::create([
                'name' => $categoryName,
                // 'icon' => ,
            ]);
        }
    }
}
