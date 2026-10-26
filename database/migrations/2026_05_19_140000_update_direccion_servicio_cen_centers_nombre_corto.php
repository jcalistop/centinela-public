<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OFFICIAL_PREFIX = 'Actividades gestionadas por la Dirección del Servicio para apoyo de la Red (';

    /**
     * Orden: frase más larga primero para no abreviar parcialmente "Centro Comunitario…".
     *
     * @var array<string, string>
     */
    private const NOMBRE_CORTO_ABBREVIATIONS = [
        'Centro Comunitario de Salud Familiar' => 'CECOSF',
        'Centro de Salud Familiar' => 'CESFAM',
        'Posta de Salud Rural' => 'PSR',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('cen_centers')) {
            return;
        }

        $this->updateDireccionServicioCenters();
        $this->abbreviateNombreCortoFields();
    }

    public function down(): void
    {
        if (! Schema::hasTable('cen_centers')) {
            return;
        }

        $this->revertAbbreviateNombreCortoFields();
        $this->revertDireccionServicioCenters();
    }

    private function updateDireccionServicioCenters(): void
    {
        $rows = DB::table('cen_centers')
            ->where('nombre_oficial', 'like', self::OFFICIAL_PREFIX.'%')
            ->get(['id', 'nombre_oficial']);

        foreach ($rows as $row) {
            $nombreOficial = $this->normalizedOfficialName($row->nombre_oficial);
            $nombreCorto = $this->applyAbbreviations($this->nombreCortoFromOficial($nombreOficial) ?? '');

            if ($nombreCorto === '') {
                continue;
            }

            $update = ['nombre_corto' => $nombreCorto];

            if ($nombreOficial !== $row->nombre_oficial) {
                $update['nombre_oficial'] = $nombreOficial;
            }

            DB::table('cen_centers')
                ->where('id', $row->id)
                ->update($update);
        }
    }

    private function revertDireccionServicioCenters(): void
    {
        $rows = DB::table('cen_centers')
            ->where('nombre_oficial', 'like', self::OFFICIAL_PREFIX.'%')
            ->orderBy('id')
            ->get(['id', 'nombre_oficial']);

        foreach ($rows as $row) {
            $nombreOficial = $this->officialNameForRollback($row->nombre_oficial);

            DB::table('cen_centers')
                ->where('id', $row->id)
                ->update([
                    'nombre_oficial' => $nombreOficial,
                    'nombre_corto' => $nombreOficial,
                ]);
        }
    }

    private function abbreviateNombreCortoFields(): void
    {
        $rows = DB::table('cen_centers')
            ->orderBy('id')
            ->get(['id', 'nombre_corto']);

        foreach ($rows as $row) {
            $nombreCorto = $this->applyAbbreviations((string) $row->nombre_corto);

            if ($nombreCorto === $row->nombre_corto) {
                continue;
            }

            DB::table('cen_centers')
                ->where('id', $row->id)
                ->update(['nombre_corto' => $nombreCorto]);
        }
    }

    private function revertAbbreviateNombreCortoFields(): void
    {
        $rows = DB::table('cen_centers')
            ->orderBy('id')
            ->get(['id', 'nombre_corto']);

        foreach ($rows as $row) {
            $nombreCorto = $this->revertAbbreviations((string) $row->nombre_corto);

            if ($nombreCorto === $row->nombre_corto) {
                continue;
            }

            DB::table('cen_centers')
                ->where('id', $row->id)
                ->update(['nombre_corto' => $nombreCorto]);
        }
    }

    private function applyAbbreviations(string $nombreCorto): string
    {
        foreach (self::NOMBRE_CORTO_ABBREVIATIONS as $from => $to) {
            $nombreCorto = str_replace($from, $to, $nombreCorto);
        }

        return $nombreCorto;
    }

    private function revertAbbreviations(string $nombreCorto): string
    {
        foreach (array_reverse(self::NOMBRE_CORTO_ABBREVIATIONS, preserve_keys: true) as $from => $to) {
            $nombreCorto = str_replace($to, $from, $nombreCorto);
        }

        return $nombreCorto;
    }

    private function normalizedOfficialName(string $nombreOficial): string
    {
        if (! str_starts_with($nombreOficial, self::OFFICIAL_PREFIX)) {
            return $nombreOficial;
        }

        if (str_ends_with($nombreOficial, ')')) {
            return $nombreOficial;
        }

        return $nombreOficial.')';
    }

    private function officialNameForRollback(string $nombreOficial): string
    {
        if (! str_starts_with($nombreOficial, self::OFFICIAL_PREFIX) || ! str_ends_with($nombreOficial, ')')) {
            return $nombreOficial;
        }

        $fragment = substr($nombreOficial, strlen(self::OFFICIAL_PREFIX));

        if (! str_contains($fragment, ')')) {
            return substr($nombreOficial, 0, -1);
        }

        return $nombreOficial;
    }

    private function nombreCortoFromOficial(string $nombreOficial): ?string
    {
        if (! str_starts_with($nombreOficial, self::OFFICIAL_PREFIX)) {
            return null;
        }

        $fragment = substr($nombreOficial, strlen(self::OFFICIAL_PREFIX));
        $fragment = rtrim($fragment, ')');
        $servicio = preg_replace('/^S\.S\s+/i', '', $fragment) ?? $fragment;

        if ($servicio === '') {
            return null;
        }

        return 'Dirección SS '.$servicio;
    }
};
