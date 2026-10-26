<?php

namespace Database\Factories;

use App\Models\CenCenter;
use App\Models\MetDataField;
use App\Models\MetMonthlyData;
use App\Models\Year;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MetMonthlyData>
 */
class MetMonthlyDataFactory extends Factory
{
    protected $model = MetMonthlyData::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = session()->get('indicator_year');
        $yearId = $year instanceof Year ? $year->id : (Year::query()->value('id') ?? 1);

        return [
            'met_data_field_id' => MetDataField::factory(),
            'cen_center_id' => CenCenter::query()->value('id') ?? 1,
            'cen_hie_dependency_id' => 1,
            'year_id' => $yearId,
            'mes' => 1,
            'valor' => 10,
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
