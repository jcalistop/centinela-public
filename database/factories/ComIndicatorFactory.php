<?php

namespace Database\Factories;

use App\Models\ComCommitment;
use App\Models\ComIndicator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComIndicator>
 */
class ComIndicatorFactory extends Factory
{
    protected $model = ComIndicator::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'com_commitment_id' => ComCommitment::factory(),
            'clave' => fake()->unique()->bothify('IND-####'),
            'nombre' => fake()->sentence(3),
            'ponderacion_indicador' => 0.2500,
        ];
    }
}
