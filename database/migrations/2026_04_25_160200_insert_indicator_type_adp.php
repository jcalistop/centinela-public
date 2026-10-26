<?php

use App\Models\IndicatorType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        IndicatorType::unguarded(function (): void {
            IndicatorType::query()->updateOrCreate(
                ['clave_indicador' => 'adp'],
                [
                    'nombre_indicador' => 'Alta Dirección Pública (ADP)',
                    'descripcion_indicador' => 'Instrumento ADP — evaluación por cargo en establecimiento (SDD-026).',
                    'icon_class' => 'voyager-logbook',
                    'route' => 'adp',
                    'view' => null,
                    'estado' => false,
                    'orden' => 5,
                    'measurement_granularity' => 'adp_cargo_establishment',
                ]
            );
        });
    }

    public function down(): void
    {
        IndicatorType::query()->where('clave_indicador', 'adp')->delete();
    }
};
