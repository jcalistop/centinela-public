<?php

namespace Database\Factories;

use App\Models\CenCenter;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\Year;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MetIndicatorConfiguration>
 */
class MetIndicatorConfigurationFactory extends Factory
{
    protected $model = MetIndicatorConfiguration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = session()->get('indicator_year');
        $yearId = $year instanceof Year ? $year->id : (Year::query()->value('id') ?? 1);

        return [
            'met_indicator_id' => MetIndicator::factory(),
            'year_id' => $yearId,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => CenCenter::query()->value('id') ?? 1,
            'met_measurement_unit_id' => null,
            'aplica' => true,
        ];
    }

    public function forSs(int $ssId): static
    {
        return $this->state(fn (array $attributes) => [
            'cen_hie_dependency_id' => $ssId,
        ]);
    }

    public function forYear(Year $year): static
    {
        return $this->state(fn (array $attributes) => [
            'year_id' => $year->id,
        ]);
    }
}
