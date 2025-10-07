<?php
namespace Database\Factories;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rating>
 */
class RatingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user    = User::where('role', 'USER')->inRandomOrder()->first();
        $booking = Booking::with('provider')->inRandomOrder()->first();
        return [
            'user_id'     => $user->id,
            'booking_id'  => $booking->id,
            'provider_id' => $booking->provider->id,
            'rating'      => $this->faker->numberBetween(1, 5),
            'review'      => $this->faker->optional()->sentence(),
        ];
    }
}
