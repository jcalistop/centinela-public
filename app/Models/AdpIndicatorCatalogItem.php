<?php

namespace App\Models;

use App\Enums\DecimalesModo;
use App\Enums\MetaOperator;
use App\Enums\Periodicidad;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\Auditable;

class AdpIndicatorCatalogItem extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'adp_indicator_catalog_items';

    protected $fillable = [
        'cen_hie_dependency_id',
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
        'sensitivity_config',
        'created_by_user_id',
    ];

    protected $casts = [
        'meta_valor' => 'decimal:4',
        'meta_reglas' => 'array',
        'umbral_inferior' => 'decimal:2',
        'umbral_superior' => 'decimal:2',
        'decimales' => 'integer',
        'orden' => 'integer',
        'estado' => 'boolean',
        'sensitivity_config' => 'array',
        'meta_operador' => MetaOperator::class,
        'periodicidad' => Periodicidad::class,
        'decimales_modo' => DecimalesModo::class,
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('adp_cat_hie', function (Builder $builder): void {
            if (! Auth::check()) {
                return;
            }

            $user = Auth::user();
            if ($user === null || $user->role?->name === 'admin') {
                return;
            }

            $builder->where('cen_hie_dependency_id', $user->cen_hie_dependency_id);
        });
    }

    /**
     * @return HasOne<AdpIndicatorCatalogFormula, $this>
     */
    public function formula(): HasOne
    {
        return $this->hasOne(AdpIndicatorCatalogFormula::class, 'adp_indicator_catalog_item_id');
    }
}
