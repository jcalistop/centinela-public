<?php

namespace Database\Factories;

use App\Enums\DecimalesModo;
use App\Models\IndicatorType;
use App\Models\MetIndicator;
use App\Models\Year;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MetIndicator>
 */
class MetIndicatorFactory extends Factory
{
    protected $model = MetIndicator::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = session()->get('indicator_year');
        $yearId = $year instanceof Year ? $year->id : (Year::query()->value('id') ?? 1);

        $indicatorTypeId = IndicatorType::query()->where('route', 'metas-18834')->value('id')
            ?? IndicatorType::query()->value('id')
            ?? 1;

        return [
            'indicator_type_id' => $indicatorTypeId,
            'clave' => fake()->unique()->bothify('MET-####'),
            'nombre' => fake()->sentence(3),
            'decimales' => 2,
            'decimales_modo' => DecimalesModo::Redondear,
            'year_id' => $yearId,
            'orden' => 1,
            'estado' => true,
        ];
    }

    public function forYear(Year $year): static
    {
        return $this->state(fn (array $attributes) => [
            'year_id' => $year->id,
        ]);
    }
}
