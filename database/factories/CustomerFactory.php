<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '(11) 99999-0000',
            'email' => fake()->unique()->safeEmail(),
            'notes' => null,
            'is_active' => true,
        ];
    }
}
