<?php

namespace App\Models;

use App\Enums\Comges\EvaluacionTipo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use OwenIt\Auditing\Contracts\Auditable;

class ComIndicatorCutoff extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    public function cutoff(): BelongsTo
    {
        return $this->belongsTo(Cutoff::class);
    }

    public function comActions(): HasMany
    {
        return $this->hasMany(ComAction::class);
    }

    public function comIndicator(): BelongsTo
    {
        return $this->belongsTo(ComIndicator::class);
    }

    public function comVisualizations(): HasMany
    {
        return $this->hasMany(ComVisualization::class);
    }

    /** @var array<string, Collection> */
    private array $cumplimientosCache = [];

    /** @var array<string, ?float> */
    private array $promedioCache = [];

    public function cumplimientosPorTipo(string $tipo): Collection
    {
        if (array_key_exists($tipo, $this->cumplimientosCache)) {
            return $this->cumplimientosCache[$tipo];
        }

        return $this->cumplimientosCache[$tipo] = $this->comActions->map(
            fn ($action) => $action->comEvaluations
                ->firstWhere('tipo_evaluacion', EvaluacionTipo::from($tipo))
                ?->cumplimiento
        );
    }

    public function promedioCumplimiento(string $tipo): ?float
    {
        if (array_key_exists($tipo, $this->promedioCache)) {
            return $this->promedioCache[$tipo];
        }

        $valores = $this->cumplimientosPorTipo($tipo);

        return $this->promedioCache[$tipo] = $valores->whereNull()->isNotEmpty() ? null : round($valores->avg(), 1);
    }

    /**
     * Igual criterio que en evaluaciones COMGES: existe al menos una tabla de sensibilidad para la acción,
     * pero ninguna está asignada al Servicio de Salud (dependencia jerárquica).
     */
    public function hasSensitivityTableSelectionPendingForDependency(int $cenHieDependencyId): bool
    {
        foreach ($this->comActions as $action) {
            if ($action->comSensitivityTables->isEmpty()) {
                continue;
            }

            $hasSelectionForDependency = $action->comSensitivityTables->contains(
                static function (ComSensitivityTable $table) use ($cenHieDependencyId): bool {
                    return $table->comSensitivityDependencies
                        ->where('cen_hie_dependency_id', $cenHieDependencyId)
                        ->isNotEmpty();
                }
            );

            if (! $hasSelectionForDependency) {
                return true;
            }
        }

        return false;
    }
}
