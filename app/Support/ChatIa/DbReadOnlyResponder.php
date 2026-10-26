<?php

namespace App\Support\ChatIa;

use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\MetEstablishmentResult;
use App\Models\MetIndicator;
use App\Models\Year;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

final class DbReadOnlyResponder
{
    private const int INDICATOR_SAMPLE_SIZE = 8;

    public function respond(array $pageContext, string $userMessage): string
    {
        if ($this->looksLikeWriteOrSqlExecutionAttempt($userMessage)) {
            return 'Puedo ayudarte solo en modo lectura. No ejecuto ni genero acciones de escritura en base de datos (UPDATE/DELETE/ALTER/INSERT, etc.).';
        }

        if (! Auth::check()) {
            return 'Necesitas iniciar sesión para consultar datos de BD desde el chat.';
        }

        $indicatorTypeId = self::positiveIntOrNull($pageContext['indicator_type_id'] ?? null);
        $yearId = self::positiveIntOrNull($pageContext['year_id'] ?? null);
        $cenHieDependencyId = self::positiveIntOrNull($pageContext['cen_hie_dependency_id'] ?? null);

        if ($indicatorTypeId === null || $yearId === null || $cenHieDependencyId === null) {
            return 'Para responder con datos de BD necesito contexto completo (instrumento, año vía sesión `indicator_year` y Servicio de Salud vía sesión/usuario). Navega a un instrumento (Metas/ADP/COMGES) o pantalla COMGES en admin, y verifica año y SS.';
        }

        $userSs = self::positiveIntOrNull(Auth::user()->cen_hie_dependency_id);

        if ($userSs === null) {
            return 'No hay Servicio de Salud para filtrar datos. Verifica que tu perfil de usuario tenga asignado un Servicio de Salud (`cen_hie_dependency_id`).';
        }

        if ($cenHieDependencyId !== $userSs) {
            return 'No puedo consultar datos porque el Servicio de Salud del contexto no coincide con tu sesión actual.';
        }

        $indicatorType = IndicatorType::query()
            ->whereKey($indicatorTypeId)
            ->where('estado', true)
            ->first();

        if (! $indicatorType instanceof IndicatorType) {
            return 'No pude resolver el instrumento activo para este contexto. Intenta recargar la página o volver a entrar al instrumento.';
        }

        $year = Year::query()->find($yearId);
        $ss = CenHieDependency::query()->find($cenHieDependencyId);

        $indicators = MetIndicator::query()
            ->select([
                'id',
                'clave',
                'nombre',
                'objetivo',
                'meta_valor',
                'meta_operador',
                'meta_anual_descripcion',
                'unidad_medida',
                'nivel_aplicacion',
                'orden',
            ])
            ->where('indicator_type_id', $indicatorTypeId)
            ->where('year_id', $yearId)
            ->where('estado', true)
            ->orderBy('orden')
            ->limit(self::INDICATOR_SAMPLE_SIZE)
            ->get();

        $result = MetEstablishmentResult::query()
            ->select(['periodo', 'resultado_global', 'n_seleccionados', 'n_calculados'])
            ->where('indicator_type_id', $indicatorTypeId)
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $cenHieDependencyId)
            ->orderByDesc('periodo')
            ->first();

        Log::info('chat_ia.db_readonly.respond', [
            'query_type' => 'context_snapshot',
            'indicator_type_id' => $indicatorTypeId,
            'year_id' => $yearId,
            'cen_hie_dependency_id' => $cenHieDependencyId,
            'route_name' => $pageContext['route_name'] ?? null,
        ]);

        $lines = [];
        $lines[] = 'Contexto (BD, solo lectura):';
        $lines[] = '- Instrumento: '.$indicatorType->nombre_indicador.' (route: '.$indicatorType->route.')';
        $lines[] = '- Año: '.($year?->id ?? $yearId);
        $lines[] = '- Servicio de Salud: '.($ss?->nombre_dependencia ?? (string) $cenHieDependencyId);

        if ($indicators->isEmpty()) {
            $lines[] = '';
            $lines[] = 'No encontré indicadores activos para este instrumento/año en BD.';
        } else {
            $lines[] = '';
            $lines[] = 'Indicadores (muestra):';
            foreach ($indicators as $row) {
                $meta = trim((string) ($row->meta_anual_descripcion ?? ''));
                if ($meta === '') {
                    $meta = trim((string) ($row->meta_operador ?? '')).' '.trim((string) ($row->meta_valor ?? ''));
                    $meta = trim($meta) !== '' ? $meta : '—';
                }

                $lines[] = '- ['.$row->clave.'] '.$row->nombre.' — meta: '.$meta;
            }
        }

        if ($result !== null) {
            $lines[] = '';
            $lines[] = 'Último resultado global disponible (Metas, por establecimiento):';
            $lines[] = '- Período: '.$result->periodo;
            $lines[] = '- Resultado global: '.(string) $result->resultado_global;
            $lines[] = '- Seleccionados / calculados: '.$result->n_seleccionados.' / '.$result->n_calculados;
        }

        $lines[] = '';
        $lines[] = 'Dime qué indicador (clave) te interesa y qué necesitas: definición, fórmula, meta, o interpretación según lo visible en pantalla.';

        return implode("\n", $lines);
    }

    private function looksLikeWriteOrSqlExecutionAttempt(string $text): bool
    {
        $t = mb_strtolower($text);

        return (bool) preg_match('/\b(update|delete|insert|alter|drop|truncate|create|grant|revoke|merge)\b/i', $t)
            || str_contains($t, 'select ')
            || str_contains($t, ';');
    }

    private static function positiveIntOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '' || is_bool($value)) {
            return null;
        }

        $filtered = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $filtered === false ? null : $filtered;
    }
}
