<?php

namespace Database\Factories;

use App\Enums\Corte\CutoffClave;
use App\Enums\Corte\CutoffTipo;
use App\Models\ComIndicator;
use App\Models\ComIndicatorCutoff;
use App\Models\Cutoff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComIndicatorCutoff>
 */
class ComIndicatorCutoffFactory extends Factory
{
    protected $model = ComIndicatorCutoff::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cutoff = Cutoff::query()->firstOrCreate(
            ['clave' => CutoffClave::CorteI],
            [
                'tipo_corte' => CutoffTipo::Trimestral,
                'meses' => json_encode([1, 2, 3]),
                'nombre_corte' => 'Corte I',
                'orden' => 1,
                'habilitado' => 1,
            ]
        );

        return [
            'com_indicator_id' => ComIndicator::factory(),
            'cutoff_id' => $cutoff->id,
            'ponderacion_corte' => 1.000,
        ];
    }
}
