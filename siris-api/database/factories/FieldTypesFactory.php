<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FieldTypes>
 */
class FieldTypesFactory extends Factory
{
    const TYPES = ['input', 'choice', 'number', 'toggle', 'radio', 'checkbox', 'textarea', 'date'];
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->sentence(),
            'type' => self::TYPES[rand(0, 6)],
            'config' => json_encode([]),
        ];
    }
}
