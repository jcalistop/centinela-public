<?php

namespace Database\Factories;

use App\Enums\Metas\DataFieldAggregationType;
use App\Enums\Metas\DataFieldComponente;
use App\Enums\Metas\DataFieldInputType;
use App\Models\MetDataField;
use App\Models\MetIndicator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MetDataField>
 */
class MetDataFieldFactory extends Factory
{
    protected $model = MetDataField::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'met_indicator_id' => MetIndicator::factory(),
            'label' => 'Campo',
            'variable_key' => 'A',
            'componente' => DataFieldComponente::Numerador,
            'input_type' => DataFieldInputType::Mensual,
            'aggregation_type' => DataFieldAggregationType::Sumatoria,
            'orden' => 0,
        ];
    }
}
