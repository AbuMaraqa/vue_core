<?php

namespace Database\Factories;

use App\Modules\Accounting\Enums\AccountSubtype;
use App\Modules\Accounting\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtype = AccountSubtype::OperatingExpense;
        $name = fake()->unique()->words(2, true);

        return [
            'parent_id' => null,
            'code' => (string) fake()->unique()->numberBetween(900000, 999999),
            'name' => ['ar' => $name, 'en' => $name],
            'type' => $subtype->type(),
            'subtype' => $subtype,
            'is_group' => false,
            'is_active' => true,
        ];
    }

    public function subtype(AccountSubtype $subtype): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $subtype->type(),
            'subtype' => $subtype,
        ]);
    }

    public function group(): static
    {
        return $this->state(fn (array $attributes) => ['is_group' => true]);
    }
}
