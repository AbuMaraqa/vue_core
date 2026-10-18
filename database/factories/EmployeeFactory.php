<?php

namespace Database\Factories;

use App\Modules\Payroll\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'E-'.fake()->unique()->numerify('#####'),
            'name' => fake()->name(),
            'hire_date' => now()->subYear()->toDateString(),
            'basic_salary' => '600',
            'payment_method' => 'bank',
            'is_active' => true,
        ];
    }
}
