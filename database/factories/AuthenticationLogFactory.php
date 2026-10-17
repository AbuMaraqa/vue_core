<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Foundation\Enums\AuthenticationEvent;
use App\Modules\Foundation\Models\AuthenticationLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuthenticationLog>
 */
class AuthenticationLogFactory extends Factory
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
            'email' => fn (array $attributes): mixed => User::query()->whereKey($attributes['user_id'])->value('email'),
            'event' => AuthenticationEvent::Login,
            'ip_address' => fake()->ipv4(),
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36',
            'created_at' => now(),
        ];
    }

    /**
     * A failed attempt for an email that matches no account.
     */
    public function failed(string $email = 'unknown@example.com'): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'email' => $email,
            'event' => AuthenticationEvent::Failed,
        ]);
    }
}
