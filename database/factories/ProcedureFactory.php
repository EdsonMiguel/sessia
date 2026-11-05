<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProcedureFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Procedimento '.fake()->word(),
            'description' => null,
            'duration_minutes' => 60,
            'default_price' => '120.00',
            'is_active' => true,
        ];
    }
}
