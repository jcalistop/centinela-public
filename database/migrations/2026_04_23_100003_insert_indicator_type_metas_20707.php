<?php

use App\Models\IndicatorType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        IndicatorType::unguarded(function (): void {
            IndicatorType::query()->updateOrCreate(
                ['clave_indicador' => 'Metas Ley 20.707'],
                [
                    'nombre_indicador' => 'Metas Ley 20.707',
                    'descripcion_indicador' => 'Instrumento Metas — Ley 20.707 (granularidad unidad interna).',
                    'icon_class' => 'voyager-pie-graph',
                    'route' => 'metas-20707',
                    'view' => null,
                    'estado' => false,
                    'orden' => 3,
                    'measurement_granularity' => 'internal_unit',
                ]
            );
        });
    }

    public function down(): void
    {
        IndicatorType::query()->where('clave_indicador', 'metas-20707')->delete();
    }
};
