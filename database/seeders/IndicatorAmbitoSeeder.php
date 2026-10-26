<?php

namespace Database\Seeders;

use App\Models\IndicatorAmbito;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class IndicatorAmbitoSeeder extends Seeder
{
    /**
     * @var list<array{nombre: string, orden: int}>
     */
    private const DEFAULTS = [
        ['nombre' => 'Hospitalización', 'orden' => 10],
        ['nombre' => 'Urgencia', 'orden' => 20],
        ['nombre' => 'Atención ambulatoria', 'orden' => 30],
        ['nombre' => 'GES', 'orden' => 40],
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $row) {
            $slug = Str::slug($row['nombre']);

            IndicatorAmbito::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'nombre' => $row['nombre'],
                    'orden' => $row['orden'],
                    'estado' => true,
                ]
            );
        }
    }
}
