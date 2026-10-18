<?php

namespace Database\Factories;

use App\Modules\Contacts\Enums\ContactKind;
use App\Modules\Contacts\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'C-'.fake()->unique()->numerify('#######'),
            'kind' => ContactKind::Company,
            'name' => fake()->unique()->company(),
            'is_customer' => true,
            'is_vendor' => false,
            'email' => fake()->optional()->companyEmail(),
            'phone' => fake()->optional()->numerify('06#######'),
            'payment_terms_days' => 0,
            'is_active' => true,
        ];
    }

    public function vendor(): static
    {
        return $this->state(fn (array $attributes) => ['is_customer' => false, 'is_vendor' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
