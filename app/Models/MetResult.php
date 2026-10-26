<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHealthService;
use App\Models\Concerns\FiltersByActiveYear;
use App\Models\Concerns\ScopesMetEtapaAutoevaluacion;
use App\Services\MetFormulaEvaluatorService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class MetResult extends Model implements Auditable
{
    use BelongsToHealthService;
    use FiltersByActiveYear;
    use HasFactory;
    use \OwenIt\Auditing\Auditable;
    use ScopesMetEtapaAutoevaluacion;

    protected static function booted(): void
    {
        static::saving(function (MetResult $result): void {
            $result->result_unit_scope_key = (int) ($result->met_measurement_unit_id ?? 0);
        });
    }

    protected $fillable = [
        'met_indicator_id',
        'cen_center_id',
        'met_measurement_unit_id',
        'result_unit_scope_key',
        'cen_hie_dependency_id',
        'year_id',
        'periodo',
        'numerador',
        'denominador',
        'resultado',
        'cumple_meta',
        'error_message',
        'etapa',
    ];

    protected $casts = [
        'etapa' => 'string',
        'numerador' => 'decimal:4',
        'denominador' => 'decimal:4',
        'resultado' => 'decimal:4',
        'cumple_meta' => 'boolean',
    ];

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(MetIndicator::class, 'met_indicator_id');
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(CenCenter::class, 'cen_center_id');
    }

    public function measurementUnit(): BelongsTo
    {
        return $this->belongsTo(MetMeasurementUnit::class, 'met_measurement_unit_id');
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }

    /**
     * Mensajes persistidos en `error_message` que representan ausencia de datos
     * para evaluar la fórmula (no un fallo de cálculo). Alineado con
     * {@see MetFormulaEvaluatorService}.
     */
    public const MESSAGE_SIN_CASOS = 'Sin casos';

    public const MESSAGE_SIN_SUJETOS_EVALUADOS = 'Error: sin sujetos evaluados';

    public const MESSAGE_DIVISOR_CERO = 'Error: divisor cero';

    public static function storedErrorMessageIndicatesMissingData(?string $message): bool
    {
        return $message === 'N/D';
    }

    public static function storedErrorMessageIndicatesNoCasesInPeriod(?string $message): bool
    {
        return $message === self::MESSAGE_SIN_CASOS;
    }

    /**
     * Mensajes que no deben disparar alertas de error de cálculo en catálogo/carga.
     */
    public static function storedErrorMessageIsNonCalculationAlert(?string $message): bool
    {
        return in_array($message, ['N/D', self::MESSAGE_SIN_CASOS], true);
    }
}
