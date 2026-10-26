<?php

namespace App\Models;

use App\Enums\Corte\CutoffClave;
use App\Enums\Indicators\IndicatorModuleType;
use App\Helpers\Helper;
use App\Models\Concerns\HasIndicatorActivities;
use App\Models\Concerns\HasIndicatorAmbitos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\Auditable;

class ComIndicator extends Model implements Auditable
{
    use HasFactory;
    use HasIndicatorActivities;
    use HasIndicatorAmbitos;
    use \OwenIt\Auditing\Auditable;

    public function comCommitment(): BelongsTo
    {
        return $this->belongsTo(ComCommitment::class, 'com_commitment_id');
    }

    public function comIndicatorCutoffs(): HasMany
    {
        return $this->hasMany(ComIndicatorCutoff::class)
            ->orderBy(
                Cutoff::select('orden')->whereColumn('cutoffs.id', 'com_indicator_cutoffs.cutoff_id')
            );
    }

    public function comIndicatorSettings(): HasMany
    {
        return $this->hasMany(ComIndicatorSetting::class);
    }

    public function scopeFilter(Builder $query): Builder
    {
        return $query->whereHas('comCommitment', fn ($subQuery) => $subQuery->where('year_id', Helper::showCurrentYear()->id));
    }

    public function scopeUser(Builder $query): Builder
    {
        $query->whereHas('comCommitment', fn ($subQuery) => $subQuery->where('year_id', Helper::showCurrentYear()->id));
        $query->whereHas('comIndicatorSettings.users', fn ($subQuery) => $subQuery->where('id', Auth::user()->id));

        return $query;
    }

    /** @var array<string, ?float> */
    private array $puntajePonderadoCache = [];

    public function puntajePonderado(string $tipo): ?float
    {
        if (array_key_exists($tipo, $this->puntajePonderadoCache)) {
            return $this->puntajePonderadoCache[$tipo];
        }

        return $this->puntajePonderadoCache[$tipo] = $this->comIndicatorCutoffs
            ->reject(fn ($cutoff) => $cutoff->cutoff->clave === CutoffClave::Apelacion)
            ->reduce(function (?float $carry, ComIndicatorCutoff $cutoff) use ($tipo) {
                if (is_null($carry)) {
                    return null;
                }

                $valores = $cutoff->cumplimientosPorTipo($tipo);

                if ($valores->whereNull()->isNotEmpty()) {
                    return null;
                }

                // El puntaje global del indicador por corte debe ser un promedio (no suma),
                // para que el resultado total se mantenga dentro de la escala 0–4.
                return $carry + $valores->avg() * ($cutoff->ponderacion_corte / 100);
            }, 0.0);
    }

    protected function indicatorActivityModule(): IndicatorModuleType
    {
        return IndicatorModuleType::Comges;
    }
}
