<?php
namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::inRandomOrder()->first()->id,
            'seller_id'   => User::where('role', 'USER')->inRandomOrder()->first()->id,
            'name'        => $this->faker->words(3, true),
            'description' => $this->faker->paragraph(),
            'price'       => $this->faker->randomFloat(2, 10, 500),
            'status'      => $this->faker->randomElement(['active', 'inactive']),
        ];
    }
}
