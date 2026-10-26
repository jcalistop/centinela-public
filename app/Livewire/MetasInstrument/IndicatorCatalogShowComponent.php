<?php

namespace App\Livewire\MetasInstrument;

use App\Enums\Metas\DataFieldSource;
use App\Livewire\MetasInstrument\Concerns\AssertsMetIndicatorCatalogScope;
use App\Livewire\MetasInstrument\Concerns\ResolvesInstrumentIndicatorTypeId;
use App\Models\IndicatorType;
use App\Models\MetDataField;
use App\Models\MetIndicator;
use App\Models\MetIndicatorReferente;
use App\Models\Role;
use App\Models\User;
use App\Services\Metas\MetFieldSsConfigService;
use App\Services\Metas\MetIndicatorPeriodicityResolver;
use App\Services\Notifications\ReferenteAssignmentMailDataFactory;
use App\Services\Notifications\ReferenteAssignmentNotifier;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class IndicatorCatalogShowComponent extends Component
{
    use AssertsMetIndicatorCatalogScope;
    use ResolvesInstrumentIndicatorTypeId;

    public int $indicatorTypeId = 0;

    public int $indicatorId = 0;

    public ?IndicatorType $indicatorTypeModel = null;

    public ?MetIndicator $indicatorModel = null;

    /** @var array<string, mixed> */
    public array $fichaData = [];

    public bool $showReferentesModal = false;

    /** @var int[] */
    public array $referentesList = [];

    public bool $notifyReferentes = false;

    public string $referenteSearch = '';

    public function mount(
        ?int $indicatorTypeId = null,
        ?int $indicatorId = null,
        ?IndicatorType $indicatorType = null,
        ?MetIndicator $indicator = null,
    ): void {
        $this->authorizeCatalogShow();

        $resolvedTypeId = $indicatorType?->id ?? $indicatorTypeId ?? ($this->indicatorTypeId > 0 ? $this->indicatorTypeId : null);
        $this->indicatorTypeId = $this->resolveInstrumentIndicatorTypeId(
            $resolvedTypeId !== null ? (int) $resolvedTypeId : null
        );

        $indicatorTypeModel = IndicatorType::query()->findOrFail($this->indicatorTypeId);
        $this->assertMetasInstrumentIndicatorType($indicatorTypeModel);
        $this->indicatorTypeModel = $indicatorTypeModel;

        $resolvedIndicatorId = $indicator?->id ?? $indicatorId ?? ($this->indicatorId > 0 ? $this->indicatorId : null);
        abort_unless($resolvedIndicatorId !== null, 404);

        $indicatorModel = $indicator ?? MetIndicator::query()->findOrFail($resolvedIndicatorId);
        $this->indicatorModel = $indicatorModel;

        $this->assertIndicatorInScope($indicatorModel, $indicatorTypeModel);

        $this->indicatorId = (int) $indicatorModel->id;

        $yearId = $this->currentIndicatorYear()->id;
        $depId = $this->authCatalogUser()->cen_hie_dependency_id;

        $indicatorModel->load([
            'dataFields',
            'formula',
            'indicatorAmbitos',
            'referentes' => fn ($q) => $q
                ->where('year_id', $yearId)
                ->where('cen_hie_dependency_id', $depId)
                ->with('user:id,name'),
        ]);

        $this->fichaData = $this->buildFichaData($indicatorModel, $depId, $yearId);
    }

    public function openReferentes(): void
    {
        $this->authorizeReferentesManage();

        $yearId = $this->currentIndicatorYear()->id;
        $depId = $this->authCatalogUser()->cen_hie_dependency_id;

        $this->referentesList = MetIndicatorReferente::query()
            ->where('met_indicator_id', $this->indicatorId)
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $depId)
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->toArray();

        $this->showReferentesModal = true;
        $this->notifyReferentes = false;
        $this->referenteSearch = '';
    }

    public function addReferente(int $userId): void
    {
        $this->authorizeReferentesManage();

        if (! in_array($userId, $this->validReferenteUserIds(), true)) {
            return;
        }

        if (! in_array($userId, $this->referentesList, true)) {
            $this->referentesList[] = $userId;
        }
    }

    public function removeReferente(int $userId): void
    {
        $this->authorizeReferentesManage();

        $this->referentesList = array_values(
            array_filter(
                $this->referentesList,
                fn (int $id): bool => $id !== $userId,
            ),
        );
    }

    public function saveReferentes(): void
    {
        $this->authorizeReferentesManage();

        $yearId = $this->currentIndicatorYear()->id;
        $depId = $this->authCatalogUser()->cen_hie_dependency_id;

        $validIds = $this->validReferenteUserIds();
        $selectedIds = array_values(array_intersect(
            array_map('intval', $this->referentesList),
            $validIds,
        ));

        $previousIds = MetIndicatorReferente::query()
            ->where('met_indicator_id', $this->indicatorId)
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $depId)
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->toArray();

        $newReferenteIds = array_values(array_diff($selectedIds, $previousIds));
        $shouldNotify = $this->notifyReferentes;

        if ($selectedIds === []) {
            MetIndicatorReferente::query()
                ->where('met_indicator_id', $this->indicatorId)
                ->where('year_id', $yearId)
                ->where('cen_hie_dependency_id', $depId)
                ->delete();
        } else {
            MetIndicatorReferente::query()
                ->where('met_indicator_id', $this->indicatorId)
                ->where('year_id', $yearId)
                ->where('cen_hie_dependency_id', $depId)
                ->whereNotIn('user_id', $selectedIds)
                ->delete();

            foreach ($selectedIds as $userId) {
                MetIndicatorReferente::firstOrCreate([
                    'met_indicator_id' => $this->indicatorId,
                    'year_id' => $yearId,
                    'cen_hie_dependency_id' => $depId,
                    'user_id' => $userId,
                ]);
            }
        }

        if ($shouldNotify && $newReferenteIds !== []) {
            $indicator = MetIndicator::query()->find($this->indicatorId);

            if ($indicator !== null) {
                $instrument = IndicatorType::query()->findOrFail($this->indicatorTypeId);
                $dependency = $this->authCatalogUser()->cenHieDependency;
                $year = $this->currentIndicatorYear();

                if ($dependency !== null) {
                    $mailData = ReferenteAssignmentMailDataFactory::forMetasIndicator(
                        $indicator,
                        $instrument,
                        $dependency,
                        $year,
                    );

                    app(ReferenteAssignmentNotifier::class)->notifyNewReferentes($newReferenteIds, $mailData);
                }
            }
        }

        $this->closeReferentesModal();
        $this->refreshReferentesInFicha();

        session()->flash('message', 'Referentes guardados correctamente.');
        session()->flash('alert-type', 'success');
    }

    public function closeReferentesModal(): void
    {
        $this->showReferentesModal = false;
        $this->referentesList = [];
        $this->notifyReferentes = false;
        $this->referenteSearch = '';
    }

    protected function authorizeReferentesManage(): void
    {
        if (! $this->authCatalogUser()->hasRole(['control-gestion', 'admin'])) {
            abort(403);
        }
    }

    /**
     * @return int[]
     */
    protected function validReferenteUserIds(): array
    {
        $referenteRole = Role::where('name', 'referente')->first();
        $depId = $this->authCatalogUser()->cen_hie_dependency_id;

        if (! $referenteRole) {
            return [];
        }

        return User::query()
            ->where('role_id', $referenteRole->id)
            ->where('cen_hie_dependency_id', $depId)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->toArray();
    }

    public function render(): View
    {
        $indicatorType = $this->indicatorTypeModel;
        $indicator = $this->indicatorModel;

        $availableReferentes = collect();
        $selectedReferentes = collect();

        if ($this->showReferentesModal) {
            $referenteRole = Role::where('name', 'referente')->first();
            $depId = $this->authCatalogUser()->cen_hie_dependency_id;

            if ($referenteRole) {
                $query = User::query()
                    ->where('role_id', $referenteRole->id)
                    ->where('cen_hie_dependency_id', $depId);

                if (trim($this->referenteSearch) !== '') {
                    $query->where('name', 'like', '%'.trim($this->referenteSearch).'%');
                }

                $availableReferentes = $query->orderBy('name')->get();
            }

            if ($this->referentesList !== []) {
                $selectedReferentes = User::query()
                    ->whereIn('id', $this->referentesList)
                    ->orderBy('name')
                    ->get()
                    ->keyBy('id');
            }
        }

        return view('livewire.metas-instrument.indicator-catalog-show-component', [
            'indicatorType' => $indicatorType,
            'indicator' => $indicator,
            'availableReferentes' => $availableReferentes,
            'selectedReferentes' => $selectedReferentes,
            'canManageReferentes' => $this->authCatalogUser()->hasRole(['control-gestion', 'admin']),
        ]);
    }

    private function refreshReferentesInFicha(): void
    {
        $yearId = $this->currentIndicatorYear()->id;
        $depId = $this->authCatalogUser()->cen_hie_dependency_id;

        $referentes = MetIndicatorReferente::query()
            ->where('met_indicator_id', $this->indicatorId)
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $depId)
            ->with('user:id,name')
            ->get()
            ->filter(fn (MetIndicatorReferente $r) => $r->user !== null)
            ->map(fn (MetIndicatorReferente $r) => [
                'id' => (int) $r->user_id,
                'name' => $r->user->name,
            ])
            ->values()
            ->all();

        $this->fichaData['referentes'] = $referentes;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFichaData(MetIndicator $indicator, int $depId, int $yearId): array
    {
        return [
            'clave' => $indicator->clave,
            'nombre' => $indicator->nombre,
            'estado' => (bool) $indicator->estado,
            'prioridad' => $indicator->prioridad,
            'periodicidad' => app(MetIndicatorPeriodicityResolver::class)
                ->resolveForCarga($indicator, $depId, $yearId)->value,
            'unidad_medida' => $indicator->unidad_medida,
            'decimales' => $indicator->decimales,
            'decimales_modo' => $indicator->decimales_modo?->value,
            'objetivo' => $indicator->objetivo,
            'meta_valor' => $indicator->meta_valor,
            'meta_operador' => $indicator->meta_operador?->value,
            'meta_anual_descripcion' => $indicator->meta_anual_descripcion,
            'meta_reglas' => $indicator->meta_reglas
                ? json_encode($indicator->meta_reglas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                : null,
            'umbral_inferior' => $indicator->umbral_inferior,
            'umbral_superior' => $indicator->umbral_superior,
            'nivel_aplicacion' => $indicator->nivel_aplicacion,
            'ambito_tags' => $indicator->indicatorAmbitos
                ->map(fn ($ambito): array => [
                    'nombre' => $ambito->nombre,
                    'slug' => $ambito->slug,
                ])
                ->values()
                ->all(),
            'exclusiones' => $indicator->exclusiones,
            'formula_expression' => $indicator->formula?->description,
            'campos' => $this->buildCamposFichaData($indicator, $depId, $yearId),
            'referentes' => $indicator->referentes
                ->filter(fn (MetIndicatorReferente $r) => $r->user !== null)
                ->map(fn (MetIndicatorReferente $r) => [
                    'id' => (int) $r->user_id,
                    'name' => $r->user->name,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return list<array{label: string, variable_key: string, componente: mixed, input_type: string, aggregation_type: string, fuente: ?string}>
     */
    private function buildCamposFichaData(MetIndicator $indicator, int $depId, int $yearId): array
    {
        $effectiveFields = app(MetFieldSsConfigService::class)->applyEffectiveTypes(
            $indicator->dataFields,
            $depId,
            $yearId,
        );

        return $effectiveFields->map(function (MetDataField $field): array {
            $source = $field->fuente !== null ? DataFieldSource::tryFrom($field->fuente) : null;
            $inputType = $field->input_type;

            return [
                'label' => $field->label,
                'variable_key' => $field->variable_key,
                'componente' => $field->componente,
                'input_type' => $inputType->label(),
                'aggregation_type' => $inputType->requiresAggregationType()
                    ? ($field->aggregation_type?->label() ?? '—')
                    : '—',
                'fuente' => $source?->label(),
            ];
        })->values()->all();
    }
}
