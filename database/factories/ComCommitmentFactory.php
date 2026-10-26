<?php

namespace Database\Factories;

use App\Models\ComCommitment;
use App\Models\Year;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComCommitment>
 */
class ComCommitmentFactory extends Factory
{
    protected $model = ComCommitment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = session()->get('indicator_year');
        $yearId = $year instanceof Year ? $year->id : (Year::query()->value('id') ?? 1);

        return [
            'indicator_type_id' => 1,
            'clave' => fake()->unique()->bothify('COM-####'),
            'nombre' => fake()->sentence(3),
            'orden' => 0,
            'year_id' => $yearId,
        ];
    }
}
