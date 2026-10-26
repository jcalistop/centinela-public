<?php

namespace App\Models;

use App\Enums\DecimalesModo;
use App\Enums\Indicators\IndicatorModuleType;
use App\Enums\MetaOperator;
use App\Enums\Metas\MetIndicatorPriority;
use App\Models\Concerns\FiltersByActiveYear;
use App\Models\Concerns\HasIndicatorActivities;
use App\Models\Concerns\HasIndicatorAmbitos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OwenIt\Auditing\Contracts\Auditable;

class MetIndicator extends Model implements Auditable
{
    use FiltersByActiveYear;
    use HasFactory;
    use HasIndicatorActivities;
    use HasIndicatorAmbitos;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'indicator_type_id',
        'clave',
        'nombre',
        'objetivo',
        'meta_anual_descripcion',
        'meta_valor',
        'meta_operador',
        'meta_reglas',
        'umbral_inferior',
        'umbral_superior',
        'nivel_aplicacion',
        'exclusiones',
        'unidad_medida',
        'decimales',
        'decimales_modo',
        'year_id',
        'orden',
        'estado',
        'prioridad',
    ];

    protected $casts = [
        'meta_valor' => 'decimal:4',
        'meta_reglas' => 'array',
        'umbral_inferior' => 'decimal:2',
        'umbral_superior' => 'decimal:2',
        'decimales' => 'integer',
        'meta_operador' => MetaOperator::class,
        'decimales_modo' => DecimalesModo::class,
        'prioridad' => MetIndicatorPriority::class,
        'orden' => 'integer',
        'estado' => 'boolean',
    ];

    /**
     * Períodos por periodicidad.
     *
     * @var array<string, array<string, int[]>>
     */
    public const PERIODOS = [
        'MENSUAL' => [
            'MES-01' => [1],
            'MES-02' => [2],
            'MES-03' => [3],
            'MES-04' => [4],
            'MES-05' => [5],
            'MES-06' => [6],
            'MES-07' => [7],
            'MES-08' => [8],
            'MES-09' => [9],
            'MES-10' => [10],
            'MES-11' => [11],
            'MES-12' => [12],
        ],
        'TRIMESTRAL' => [
            'TRIM-1' => [1, 2, 3],
            'TRIM-2' => [4, 5, 6],
            'TRIM-3' => [7, 8, 9],
            'TRIM-4' => [10, 11, 12],
        ],
        'SEMESTRAL' => [
            'SEM-1' => [1, 2, 3, 4, 5, 6],
            'SEM-2' => [7, 8, 9, 10, 11, 12],
        ],
        'ANUAL' => [
            'ANUAL' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12],
        ],
    ];

    public function scopeActivo(Builder $query): Builder
    {
        return $query->where('estado', true);
    }

    public function indicatorType(): BelongsTo
    {
        return $this->belongsTo(IndicatorType::class);
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }

    public function configurations(): HasMany
    {
        return $this->hasMany(MetIndicatorConfiguration::class);
    }

    public function referentes(): HasMany
    {
        return $this->hasMany(MetIndicatorReferente::class);
    }

    public function dataFields(): HasMany
    {
        return $this->hasMany(MetDataField::class)->orderBy('orden');
    }

    public function formula(): HasOne
    {
        return $this->hasOne(MetFormula::class);
    }

    protected function indicatorActivityModule(): IndicatorModuleType
    {
        return IndicatorModuleType::Metas;
    }
}
