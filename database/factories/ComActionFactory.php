<?php

namespace Database\Factories;

use App\Models\ComAction;
use App\Models\ComIndicatorCutoff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComAction>
 */
class ComActionFactory extends Factory
{
    protected $model = ComAction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'com_indicator_cutoff_id' => ComIndicatorCutoff::factory(),
            'numero' => '1',
        ];
    }
}
