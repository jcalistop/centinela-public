<?php

use App\Models\IndicatorType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        IndicatorType::unguarded(function (): void {
            IndicatorType::query()->updateOrCreate(
                ['clave_indicador' => 'Metas Ley 18.834'],
                [
                    'nombre_indicador' => 'Metas Sanitarias Ley 18.834',
                    'descripcion_indicador' => 'Instrumento Metas — Ley 18.834 (granularidad establecimiento).',
                    'icon_class' => 'voyager-pie-chart',
                    'route' => 'metas-18834',
                    'view' => null,
                    'estado' => false,
                    'orden' => 2,
                    'measurement_granularity' => 'establishment',
                ]
            );
        });
    }

    public function down(): void
    {
        IndicatorType::query()->where('clave_indicador', 'metas-18834')->delete();
    }
};
