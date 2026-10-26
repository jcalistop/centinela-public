<?php

namespace App\Services\Agent;

use App\Models\ComAction;
use App\Models\ComCommitment;
use App\Models\ComIndicator;
use App\Models\ComIndicatorCutoff;
use App\Models\ComIndicatorSetting;
use App\Models\ComSensitivityDependency;
use App\Models\ComSensitivityTable;
use App\Models\IndicatorTypeSetting;
use App\Models\User;
use App\Models\Year;
use App\Support\Comges\ComgesYearMode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ComgesReadAdapter
{
    public const INSTRUMENTO = 'comges';

    /**
     * @return LengthAwarePaginator<int, ComIndicator>
     */
    public function paginateIndicators(Year $year, ?string $q, int $perPage): LengthAwarePaginator
    {
        $query = ComIndicator::query()
            ->whereHas('comCommitment', fn (Builder $b) => $b->where('year_id', $year->id))
            ->with(['comCommitment:id,clave,nombre,year_id,orden'])
            ->orderBy('orden')
            ->orderBy('clave');

        if ($q !== null && $q !== '') {
            $like = '%'.$q.'%';
            $query->where(function (Builder $b) use ($like): void {
                $b->where('clave', 'like', $like)
                    ->orWhere('nombre', 'like', $like);
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Indicadores COMGES del catálogo del año (sin filtrar por SS).
     */
    public function countIndicators(Year $year): int
    {
        return ComIndicator::query()
            ->whereHas('comCommitment', fn (Builder $b) => $b->where('year_id', $year->id))
            ->count();
    }

    public function findIndicator(Year $year, int $id, ?int $ssId = null): ?ComIndicator
    {
        $query = ComIndicator::query()
            ->whereKey($id)
            ->whereHas('comCommitment', fn (Builder $b) => $b->where('year_id', $year->id))
            ->with([
                'comCommitment',
                'comIndicatorCutoffs.cutoff',
                'comIndicatorCutoffs.comActions.comSensitivityTables.comSensitivityDependencies',
            ]);

        if ($ssId !== null) {
            $query->with([
                'comIndicatorSettings' => fn ($settings) => $settings
                    ->where('cen_hie_dependency_id', $ssId)
                    ->with('users:id,name'),
            ]);
        }

        return $query->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function mapListItem(ComIndicator $indicator): array
    {
        return [
            'id' => $indicator->id,
            'instrumento' => self::INSTRUMENTO,
            'clave' => $indicator->clave,
            'nombre' => $indicator->nombre,
            'compromiso_id' => $indicator->com_commitment_id,
            'compromiso_clave' => $indicator->comCommitment?->clave,
            'orden' => $indicator->orden,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mapDetail(ComIndicator $indicator, Year $year, int $ssId = 0): array
    {
        $usesScore = ComgesYearMode::usesScoreScale($year);
        $etapa = IndicatorTypeSetting::getData(1)?->etapa_vigente;
        $etapaValue = $etapa?->value ?? (string) $etapa;

        $cutoffs = $indicator->comIndicatorCutoffs->map(function (ComIndicatorCutoff $cutoff) use ($etapaValue, $ssId) {
            $cutoff->loadMissing('cutoff', 'comActions.comSensitivityTables.comSensitivityDependencies');
            $clave = $cutoff->cutoff?->clave;
            $claveValue = $clave instanceof \BackedEnum ? $clave->value : $clave;

            return [
                'id' => $cutoff->id,
                'cutoff_id' => $cutoff->cutoff_id,
                'clave' => $claveValue,
                'nombre' => $cutoff->cutoff?->nombre_corte,
                'ponderacion_corte' => $cutoff->ponderacion_corte,
                'editable' => $etapaValue !== '' && $etapaValue !== null && (string) $claveValue === (string) $etapaValue,
                'acciones' => $cutoff->comActions->map(fn (ComAction $action) => [
                    'id' => $action->id,
                    'numero' => $action->numero,
                    'meta_texto' => $action->meta_texto,
                    'formula' => $action->formula,
                    'tipo_medio' => $action->tipo_medio?->value ?? $action->tipo_medio,
                    'umbrales' => $this->mapUmbrales($action, $ssId),
                ])->values()->all(),
            ];
        })->values()->all();

        return [
            'id' => $indicator->id,
            'instrumento' => self::INSTRUMENTO,
            'clave' => $indicator->clave,
            'nombre' => $indicator->nombre,
            'compromiso_id' => $indicator->com_commitment_id,
            'compromiso_clave' => $indicator->comCommitment?->clave,
            'escala' => $usesScore ? 'score_0_4' : 'percent_0_100',
            'escala_nota' => $usesScore
                ? 'Desde 2025 COMGES usa puntaje 0–4.'
                : 'Antes de 2025 COMGES usa porcentaje 0–100.',
            'referentes' => $this->mapReferentes($indicator, $ssId),
            'cutoffs' => $cutoffs,
        ];
    }

    /**
     * Tablas de sensibilidad de la acción, con la selección del SS del token.
     *
     * @return list<array{id: int, meta_numero: float|null, valores: list<array<string, mixed>>, seleccionada_ss: bool}>
     */
    private function mapUmbrales(ComAction $action, int $ssId): array
    {
        return $action->comSensitivityTables
            ->map(function (ComSensitivityTable $table) use ($ssId): array {
                $seleccionada = $table->comSensitivityDependencies->contains(
                    fn (ComSensitivityDependency $dependency): bool => (int) $dependency->cen_hie_dependency_id === $ssId
                );

                return [
                    'id' => $table->id,
                    'meta_numero' => $table->meta_numero === null ? null : (float) $table->meta_numero,
                    'valores' => $table->normalizedValores(),
                    'seleccionada_ss' => $seleccionada,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Referentes del indicador para el SS del token (sin email ni otros PII).
     *
     * @return list<array{id: int, name: string}>
     */
    private function mapReferentes(ComIndicator $indicator, int $ssId): array
    {
        $settings = $indicator->relationLoaded('comIndicatorSettings')
            ? $indicator->comIndicatorSettings
            : $indicator->comIndicatorSettings()->where('cen_hie_dependency_id', $ssId)->with('users:id,name')->get();

        return $settings
            ->filter(fn (ComIndicatorSetting $setting): bool => (int) $setting->cen_hie_dependency_id === $ssId)
            ->flatMap(fn (ComIndicatorSetting $setting) => $setting->users)
            ->unique('id')
            ->map(fn (User $user): array => [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return LengthAwarePaginator<int, ComCommitment>
     */
    public function paginateCommitments(Year $year, ?string $q, int $perPage): LengthAwarePaginator
    {
        $query = ComCommitment::query()
            ->where('year_id', $year->id)
            ->withCount('comIndicators')
            ->orderBy('orden')
            ->orderBy('clave');

        if ($q !== null && $q !== '') {
            $like = '%'.$q.'%';
            $query->where(function (Builder $b) use ($like): void {
                $b->where('clave', 'like', $like)
                    ->orWhere('nombre', 'like', $like);
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * @return array<string, mixed>
     */
    public function mapCommitment(ComCommitment $commitment): array
    {
        return [
            'id' => $commitment->id,
            'instrumento' => self::INSTRUMENTO,
            'clave' => $commitment->clave,
            'nombre' => $commitment->nombre,
            'orden' => $commitment->orden,
            'indicadores_count' => (int) ($commitment->com_indicators_count ?? $commitment->comIndicators()->count()),
        ];
    }
}
