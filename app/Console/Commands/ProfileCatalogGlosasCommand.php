<?php

namespace App\Console\Commands;

use App\Enums\Cen\CenCenterAdmDependency;
use App\Enums\Cen\CenCenterCertification;
use App\Enums\Cen\CenCenterEmergencyType;
use App\Enums\Cen\CenCenterProviderType;
use App\Enums\Cen\CenCenterScope;
use App\Enums\Cen\CenCenterType;
use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Enums\Cen\CentroNivelAtencion;
use App\Enums\Cen\CentroNivelComplejidad;
use App\Enums\Cen\CentroPertenenciaSnss;
use App\Enums\Cen\CentroTieneUrgencia;
use App\Enums\Cen\CentroTipoAtencion;
use App\Enums\Cen\CentroTipoSapu;
use App\Enums\Cen\DireccionVia;
use App\Services\Cen\NationalEstablishmentCatalogClient;
use App\Support\Cen\CenCatalogGlosaResolver;
use BackedEnum;
use Illuminate\Console\Command;

class ProfileCatalogGlosasCommand extends Command
{
    protected $signature = 'cen:profile-catalog-glosas
                            {--ss= : Código del Servicio de Salud (filtro CKAN)}
                            {--limit=0 : Máximo de registros a analizar (0 = todos)}
                            {--output=table : table|json}';

    protected $description = 'Perfil de glosas CKAN por campo: distinct values y resultado del pipeline de resolución';

    public function handle(NationalEstablishmentCatalogClient $client): int
    {
        if (! $client->isEnabled()) {
            $this->error('Sincronización nacional deshabilitada en settings (module.national_establishments_enabled).');

            return self::FAILURE;
        }

        $ss = $this->option('ss');
        $ssCodigo = is_string($ss) && $ss !== '' ? $ss : null;
        $limit = max(0, (int) $this->option('limit'));

        /** @var array<string, array<string, array{count: int, outcomes: array<string, int>}>> $profile */
        $profile = [];

        $enumByField = $this->enumClassesByField();
        $processed = 0;

        foreach ($client->fetchRecords($ssCodigo) as $record) {
            if ($limit > 0 && $processed >= $limit) {
                break;
            }

            $processed++;

            foreach (CenCatalogGlosaResolver::API_FIELD_BY_KEY as $fieldKey => $apiColumn) {
                if (! isset($enumByField[$fieldKey])) {
                    continue;
                }

                $raw = $record[$apiColumn] ?? null;
                $rawKey = is_scalar($raw) ? (string) $raw : '';

                if (! isset($profile[$fieldKey][$rawKey])) {
                    $profile[$fieldKey][$rawKey] = ['count' => 0, 'outcomes' => []];
                }

                $profile[$fieldKey][$rawKey]['count']++;

                $resolution = CenCatalogGlosaResolver::resolve($fieldKey, $enumByField[$fieldKey], $raw);
                $outcome = $resolution->isUnknown
                    ? 'desconocido'
                    : ($resolution->equivalenceApplied ? 'equiv' : 'ok');
                $profile[$fieldKey][$rawKey]['outcomes'][$outcome] = ($profile[$fieldKey][$rawKey]['outcomes'][$outcome] ?? 0) + 1;
            }
        }

        if ($this->option('output') === 'json') {
            $this->line(json_encode([
                'records_processed' => $processed,
                'fields' => $profile,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info("Registros analizados: {$processed}");

        foreach ($profile as $fieldKey => $glosas) {
            $this->newLine();
            $this->info("Campo: {$fieldKey}");

            $rows = [];
            foreach ($glosas as $glosa => $stats) {
                $rows[] = [
                    'glosa' => $glosa === '' ? '(vacío)' : $glosa,
                    'count' => $stats['count'],
                    'ok' => $stats['outcomes']['ok'] ?? 0,
                    'equiv' => $stats['outcomes']['equiv'] ?? 0,
                    'desconocido' => $stats['outcomes']['desconocido'] ?? 0,
                ];
            }

            usort($rows, fn (array $a, array $b): int => $b['count'] <=> $a['count']);
            $this->table(['Glosa API', 'Filas', 'OK', 'Equiv', 'Desconocido'], $rows);
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, class-string<BackedEnum>>
     */
    private function enumClassesByField(): array
    {
        return [
            'estado_funcionamiento' => CentroEstadoFuncionamiento::class,
            'pertenencia_snss' => CentroPertenenciaSnss::class,
            'cen_type' => CenCenterType::class,
            'cen_scope' => CenCenterScope::class,
            'cen_certification' => CenCenterCertification::class,
            'cen_adm_dependency' => CenCenterAdmDependency::class,
            'nivel_atencion' => CentroNivelAtencion::class,
            'tiene_urgencia' => CentroTieneUrgencia::class,
            'cen_emergency_type' => CenCenterEmergencyType::class,
            'tipo_sapu' => CentroTipoSapu::class,
            'cen_provider_type' => CenCenterProviderType::class,
            'nivel_complejidad' => CentroNivelComplejidad::class,
            'tipo_atencion' => CentroTipoAtencion::class,
            'via' => DireccionVia::class,
        ];
    }
}
