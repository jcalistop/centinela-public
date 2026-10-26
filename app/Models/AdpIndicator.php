<?php

namespace App\Models;

use App\Enums\DecimalesModo;
use App\Enums\MetaOperator;
use App\Enums\Periodicidad;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OwenIt\Auditing\Contracts\Auditable;

class AdpIndicator extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'adp_objective_id',
        'adp_indicator_catalog_item_id',
        'clave',
        'nombre',
        'objetivo',
        'meta_anual_descripcion',
        'meta_valor',
        'meta_operador',
        'meta_reglas',
        'umbral_inferior',
        'umbral_superior',
        'formula_descripcion',
        'fuente_numerador',
        'fuente_denominador',
        'periodicidad',
        'nivel_aplicacion',
        'unidad_medida',
        'decimales',
        'decimales_modo',
        'calculation_method',
        'orden',
        'estado',
    ];

    protected $casts = [
        'meta_valor' => 'decimal:4',
        'meta_reglas' => 'array',
        'umbral_inferior' => 'decimal:2',
        'umbral_superior' => 'decimal:2',
        'decimales' => 'integer',
        'orden' => 'integer',
        'estado' => 'boolean',
        'meta_operador' => MetaOperator::class,
        'periodicidad' => Periodicidad::class,
        'decimales_modo' => DecimalesModo::class,
    ];

    /**
     * @return BelongsTo<AdpObjective, $this>
     */
    public function objective(): BelongsTo
    {
        return $this->belongsTo(AdpObjective::class, 'adp_objective_id');
    }

    /**
     * @return BelongsTo<AdpIndicatorCatalogItem, $this>
     */
    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(AdpIndicatorCatalogItem::class, 'adp_indicator_catalog_item_id');
    }

    /**
     * @return HasOne<AdpIndicatorFormula, $this>
     */
    public function formula(): HasOne
    {
        return $this->hasOne(AdpIndicatorFormula::class, 'adp_indicator_id');
    }

    /**
     * @return HasOne<AdpIndicatorTarget, $this>
     */
    public function target(): HasOne
    {
        return $this->hasOne(AdpIndicatorTarget::class, 'adp_indicator_id');
    }

    /**
     * @return HasMany<AdpMonthlyData, $this>
     */
    public function monthlyDataRows(): HasMany
    {
        return $this->hasMany(AdpMonthlyData::class, 'adp_indicator_id');
    }

    /**
     * @return HasOne<AdpSensitivityTable, $this>
     */
    public function sensitivityTable(): HasOne
    {
        return $this->hasOne(AdpSensitivityTable::class, 'adp_indicator_id');
    }

    /**
     * @return HasMany<AdpIndicatorResult, $this>
     */
    public function indicatorResults(): HasMany
    {
        return $this->hasMany(AdpIndicatorResult::class, 'adp_indicator_id');
    }
}
