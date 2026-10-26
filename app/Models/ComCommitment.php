<?php

namespace App\Models;

use App\Models\Concerns\FiltersByActiveYear;
use App\Support\YearContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class ComCommitment extends Model implements Auditable
{
    use FiltersByActiveYear;
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    public function save(array $options = [])
    {
        $this->indicator_type_id = 1;
        $this->year_id = app(YearContext::class)->requireCurrent()->id;
        parent::save();
    }

    public function comIndicators(): HasMany
    {
        return $this->hasMany(ComIndicator::class)->orderBy('orden')->orderBy('clave');
    }

    public function indicatorType(): BelongsTo
    {
        return $this->belongsTo(IndicatorType::class);
    }

    public function scopeCurrentSetting(Builder $query): Builder
    {
        return $query;
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }

    /** @var array<string, ?float> */
    private array $puntajePonderadoCache = [];

    public function puntajePonderado(string $tipo): ?float
    {
        if (array_key_exists($tipo, $this->puntajePonderadoCache)) {
            return $this->puntajePonderadoCache[$tipo];
        }

        $indicadores = $this->comIndicators;
        $totalPonderacion = $indicadores->sum('ponderacion_indicador');

        if ($totalPonderacion == 0) {
            return null;
        }

        $suma = $indicadores->reduce(function (?float $carry, ComIndicator $indicator) use ($tipo) {
            if (is_null($carry)) {
                return null;
            }

            $puntaje = $indicator->puntajePonderado($tipo);

            if (is_null($puntaje)) {
                return null;
            }

            return $carry + $indicator->ponderacion_indicador * $puntaje;
        }, 0.0);

        return $this->puntajePonderadoCache[$tipo] = is_null($suma) ? null : $suma / $totalPonderacion;
    }
}
