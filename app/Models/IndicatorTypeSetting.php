<?php

namespace App\Models;

use App\Enums\IndicatorType\EtapaVigente;
use App\Enums\IndicatorType\TipoEvaluacion;
use App\Helpers\Helper;
use App\Models\Concerns\BelongsToHealthService;
use App\Services\Dashboard\PublicSsDashboardService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\Auditable;

class IndicatorTypeSetting extends Model implements Auditable
{
    use BelongsToHealthService;
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'indicator_type_id',
        'year_id',
        'cen_hie_dependency_id',
        'etapa_vigente',
        'tipo_evaluacion',
        'habilitado',
        'panel_minsal',
        'texto_enriquecido',
    ];

    protected $casts = [
        'etapa_vigente' => EtapaVigente::class,
        'tipo_evaluacion' => TipoEvaluacion::class,
        'habilitado' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $setting): void {
            if ($setting->tipo_evaluacion === null) {
                $setting->tipo_evaluacion = TipoEvaluacion::NoDefinido;
            }

            if ($setting->etapa_vigente === null) {
                $setting->etapa_vigente = $setting->isMetasInstrument()
                    ? EtapaVigente::NoIniciado
                    : EtapaVigente::Cerrado;
            }

            if ($setting->isMetasInstrument() && $setting->etapa_vigente !== null) {
                $setting->habilitado = $setting->etapa_vigente->habilitaAccesoReferente();
            }
        });

        $forgetPublicCache = static function (self $setting): void {
            if ($setting->cen_hie_dependency_id === null || $setting->year_id === null) {
                return;
            }

            PublicSsDashboardService::forgetFor(
                (int) $setting->cen_hie_dependency_id,
                (int) $setting->year_id
            );
        };

        static::saved($forgetPublicCache);
        static::deleted($forgetPublicCache);
    }

    public static function getData(int $id): self
    {
        $isMetas = IndicatorType::query()
            ->whereKey($id)
            ->where('route', 'like', 'metas-%')
            ->exists();

        return self::firstOrCreate(
            [
                'indicator_type_id' => $id,
                'year_id' => Helper::showCurrentYear()->id,
                'cen_hie_dependency_id' => Auth::user()->cen_hie_dependency_id,
            ],
            [
                'tipo_evaluacion' => TipoEvaluacion::NoDefinido,
                'etapa_vigente' => $isMetas ? EtapaVigente::NoIniciado : EtapaVigente::Cerrado,
                'habilitado' => false,
            ],
        );
    }

    public function isMetasInstrument(): bool
    {
        if ($this->relationLoaded('indicatorType')) {
            $route = $this->indicatorType?->route;

            return is_string($route) && str_starts_with($route, 'metas-');
        }

        return IndicatorType::query()
            ->whereKey($this->indicator_type_id)
            ->where('route', 'like', 'metas-%')
            ->exists();
    }

    public function indicatorType(): BelongsTo
    {
        return $this->belongsTo(IndicatorType::class);
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }

    public function cutoff(): HasMany
    {
        return $this->hasMany(Cutoff::class, 'tipo_corte', 'tipo_corte');
    }

    public function cenHieDependency(): BelongsTo
    {
        return $this->belongsTo(CenHieDependency::class, 'cen_hie_dependency_id');
    }
}
