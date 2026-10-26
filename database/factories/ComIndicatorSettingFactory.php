<?php

namespace Database\Factories;

use App\Models\ComIndicator;
use App\Models\ComIndicatorSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComIndicatorSetting>
 */
class ComIndicatorSettingFactory extends Factory
{
    protected $model = ComIndicatorSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cen_hie_dependency_id' => 1,
            'com_indicator_id' => ComIndicator::factory(),
        ];
    }

    public function forSs(int $ssId): static
    {
        return $this->state(fn (array $attributes) => [
            'cen_hie_dependency_id' => $ssId,
        ]);
    }
}
