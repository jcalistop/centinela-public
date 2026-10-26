<?php

use App\Models\IndicatorType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        IndicatorType::unguarded(function (): void {
            IndicatorType::query()->updateOrCreate(
                ['clave_indicador' => 'Metas Ley 19.664'],
                [
                    'nombre_indicador' => 'Metas Sanitarias Ley 19.664',
                    'descripcion_indicador' => 'Instrumento Metas — Ley 19.664 (granularidad establecimiento).',
                    'icon_class' => 'voyager-dashboard',
                    'route' => 'metas-19664',
                    'view' => null,
                    'estado' => false,
                    'orden' => 4,
                    'measurement_granularity' => 'establishment',
                ]
            );
        });
    }

    public function down(): void
    {
        IndicatorType::query()->where('clave_indicador', 'metas-19664')->delete();
    }
};
