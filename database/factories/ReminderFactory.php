<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Foundation\Models\Reminder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reminder>
 */
class ReminderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'date' => now()->toDateString(),
            'color' => 'brand',
            'is_shared' => false,
        ];
    }

    public function shared(): static
    {
        return $this->state(fn (array $attributes) => ['is_shared' => true]);
    }
}
