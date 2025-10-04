<?php
namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user_id     = User::where('role', 'USER')->inRandomOrder()->first()?->id;
        $provider_id = User::where('role', 'PROVIDER')->inRandomOrder()->first()?->id;
        return [
            'user_id'     => $user_id,
            'provider_id' => $provider_id,
        ];
    }
}
