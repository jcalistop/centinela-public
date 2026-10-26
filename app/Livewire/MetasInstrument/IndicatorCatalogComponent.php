<?php

namespace App\Livewire\MetasInstrument;

use App\Livewire\MetasInstrument\Concerns\AssertsMetIndicatorCatalogScope;
use App\Livewire\MetasInstrument\Concerns\ResolvesInstrumentIndicatorTypeId;
use App\Models\IndicatorAmbito;
use App\Models\IndicatorType;
use App\Models\MetIndicator;
use App\Models\User;
use App\Models\Year;
use App\Services\Indicators\IndicatorAmbitoAssignmentService;
use App\Services\Metas\MetFieldSsConfigService;
use App\Services\Metas\MetIndicatorCalculationErrorService;
use App\Services\Metas\MetIndicatorDesempenoByEstablishmentService;
use App\Services\Metas\MetIndicatorDuplicationService;
use App\Services\Metas\MetIndicatorFormulaIssueService;
use App\Services\Metas\MetIndicatorIncompletePeriodService;
use App\Services\Metas\MetIndicatorLastLoadedMonthService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;

class IndicatorCatalogComponent extends Component
{
    use AssertsMetIndicatorCatalogScope;
    use ResolvesInstrumentIndicatorTypeId;
    use WithoutUrlPagination;
    use WithPagination;

    private const CATALOG_PER_PAGE = 20;

    protected string $paginationTheme = 'voyager-bootstrap';

    public string $search = '';

    public string $ambitoFilter = '';

    public bool $sortMode = false;

    /** @var Collection<int, IndicatorAmbito>|null */
    private ?Collection $cachedAmbitoOptions = null;

    // Modal duplicar indicador
    public bool $showDuplicateModal = false;

    public ?int $duplicateSourceId = null;

    public string $duplicateSourceLabel = '';

    public int $duplicateYearId;

    public int $duplicateIndicatorTypeId;

    public string $duplicateClave = '';

    public bool $duplicateClaveExists = false;

    public string $duplicateClaveError = '';

    /** @var Collection<int, Year>|null */
    public ?Collection $yearOptions = null;

    /** @var Collection<int, IndicatorType>|null */
    public ?Collection $indicatorTypeOptions = null;

    public int $indicatorTypeId;

    public ?int $yearId = null;

    public function mount(?int $indicatorTypeId = null): void
    {
        $this->indicatorTypeId = $this->resolveInstrumentIndicatorTypeId($indicatorTypeId);
        $this->yearId = $this->currentIndicatorYear()->id;
        $this->duplicateYearId = $this->yearId;
        $this->duplicateIndicatorTypeId = $this->indicatorTypeId;
    }

    public function openDuplicateModal(int $indicatorId): void
    {
        $this->authorizeParametrizador();

        $indicator = $this->catalogIndicatorsQuery()->whereKey($indicatorId)->firstOrFail();
        $this->duplicateSourceId = $indicatorId;
        $this->duplicateSourceLabel = '['.$indicator->clave.'] '.$indicator->nombre;
        $this->duplicateClave = '';
        $this->duplicateClaveExists = false;
        $this->duplicateClaveError = '';
        $this->duplicateYearId = $this->yearId;
        $this->duplicateIndicatorTypeId = $this->indicatorTypeId;
        $this->yearOptions = Year::query()->orderBy('ano', 'desc')->get();
        $this->indicatorTypeOptions = IndicatorType::query()
            ->whereNotNull('route')
            ->where('route', 'like', 'metas-%')
            ->orderBy('nombre_indicador')
            ->get();
        $this->showDuplicateModal = true;
    }

    public function updatedDuplicateClave(string $value): void
    {
        $this->duplicateClaveError = '';
        $this->duplicateClaveExists = false;

        $value = trim($value);

        if ($value === '') {
            return;
        }

        if (MetIndicator::query()->where('clave', $value)->exists()) {
            $this->duplicateClaveExists = true;
            $this->duplicateClaveError = "El código \"{$value}\" ya está en uso.";
        }
    }

    public function duplicateIndicator(): void
    {
        $this->authorizeParametrizador();

        $this->validate([
            'duplicateClave' => ['required', 'string', 'max:255'],
            'duplicateYearId' => ['required', 'integer', 'exists:years,id'],
            'duplicateIndicatorTypeId' => ['required', 'integer', 'exists:indicator_types,id'],
        ]);

        $source = $this->catalogIndicatorsQuery()->whereKey($this->duplicateSourceId)->firstOrFail();

        try {
            app(MetIndicatorDuplicationService::class)->duplicate(
                $source,
                $this->duplicateYearId,
                $this->duplicateIndicatorTypeId,
                trim($this->duplicateClave),
            );
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        $this->closeDuplicateModal();

        session()->flash('catalog_message', 'Indicador duplicado correctamente.');

        $sourceType = IndicatorType::query()->findOrFail($this->indicatorTypeId);
        $this->redirect(route('metas.instrument.index', [$sourceType]));
    }

    public function closeDuplicateModal(): void
    {
        $this->showDuplicateModal = false;
        $this->duplicateSourceId = null;
        $this->duplicateSourceLabel = '';
        $this->duplicateClave = '';
        $this->duplicateClaveExists = false;
        $this->duplicateClaveError = '';
        $this->yearOptions = null;
        $this->indicatorTypeOptions = null;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingAmbitoFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $search = $this->sortMode ? '' : trim($this->search);

        $depId = $this->authUser()->cen_hie_dependency_id;

        $indicatorsQuery = app(IndicatorAmbitoAssignmentService::class)
            ->applyToQuery(
                $this->catalogIndicatorsQuery()
                    ->when($search !== '', function ($q) use ($search) {
                        $q->where(function ($qq) use ($search) {
                            $qq->where('clave', 'like', '%'.$search.'%')
                                ->orWhere('nombre', 'like', '%'.$search.'%');
                        });
                    }),
                $this->ambitoFilter,
            )
            ->with([
                'referentes' => fn ($q) => $q
                    ->where('year_id', $this->yearId)
                    ->where('cen_hie_dependency_id', $depId)
                    ->with('user:id,name'),
                'dataFields',
                'formula',
                'indicatorType',
                'indicatorAmbitos',
            ])
            ->orderBy('orden')
            ->orderBy('clave');

        // Browse: paginado. Sort mode (DnD): set completo (SDD-171 / paridad SDD-079).
        $indicators = $this->sortMode
            ? $indicatorsQuery->get()
            : $indicatorsQuery->paginate(self::CATALOG_PER_PAGE);

        /** @var Collection<int, MetIndicator> $indicatorsForServices */
        $indicatorsForServices = $this->sortMode
            ? $indicators
            : $indicators->getCollection();

        $metasInstrument = IndicatorType::query()->findOrFail($this->indicatorTypeId);

        $lastLoadedMonthByIndicator = collect();
        $fieldSsConfigStatusByIndicator = collect();
        $indicatorsWithCalculationErrors = collect();
        $indicatorsWithIncompletePeriods = collect();
        $formulaIssuesByIndicator = collect();
        $authUser = Auth::user();

        $showsOperationalColumn = $authUser instanceof User
            && $authUser->hasRole(['control-gestion', 'admin', 'referente', 'visualizador']);

        // Cuando el modal de duplicar está abierto, se saltan los servicios costosos
        // de datos operacionales. La tabla se renderiza sin las columnas de estado
        // mientras el modal la cubre con el backdrop.
        $promedioCumplimientoByIndicator = collect();

        if ($showsOperationalColumn && ! $this->showDuplicateModal) {
            $indicatorIds = $indicatorsForServices->pluck('id')->map(fn ($id): int => (int) $id)->all();

            $lastLoadedMonthByIndicator = app(MetIndicatorLastLoadedMonthService::class)->lastMonthByIndicatorIds(
                $indicatorIds,
                (int) $depId,
                (int) $this->yearId,
                ! $metasInstrument->usesInternalUnitMeasurement(),
            );

            $fieldSsConfigStatusByIndicator = app(MetFieldSsConfigService::class)->configurationStatusByIndicators(
                $indicatorsForServices,
                (int) $depId,
                (int) $this->yearId,
            );

            // Un solo buildBatch: cumplimiento + errores agregados (SDD-169).
            $desempenoBatch = [];
            if ($indicatorsForServices->isNotEmpty()) {
                $desempenoBatch = app(MetIndicatorDesempenoByEstablishmentService::class)
                    ->buildBatchForIndicators($indicatorsForServices, (int) $depId, (int) $this->yearId);

                foreach ($desempenoBatch as $indicatorId => $payload) {
                    $promedioCumplimientoByIndicator->put(
                        (int) $indicatorId,
                        $payload['promedio_cumplimiento'],
                    );
                }
            }

            if ($indicatorIds !== []) {
                $indicatorsWithCalculationErrors = app(MetIndicatorCalculationErrorService::class)
                    ->indicatorIdsWithCalculationErrors(
                        $indicatorIds,
                        (int) $depId,
                        (int) $this->yearId,
                        $desempenoBatch,
                    );

                $indicatorsWithIncompletePeriods = app(MetIndicatorIncompletePeriodService::class)
                    ->indicatorIdsWithIncompletePeriods($indicatorIds, (int) $depId, (int) $this->yearId);
            }
        }

        $formulaIssuesByIndicator = app(MetIndicatorFormulaIssueService::class)
            ->issuesByIndicatorId($indicatorsForServices);

        return view('livewire.metas-instrument.indicator-catalog', compact(
            'indicators',
            'metasInstrument',
            'lastLoadedMonthByIndicator',
            'fieldSsConfigStatusByIndicator',
            'indicatorsWithCalculationErrors',
            'indicatorsWithIncompletePeriods',
            'formulaIssuesByIndicator',
            'showsOperationalColumn',
            'promedioCumplimientoByIndicator',
        ) + [
            'indicatorAmbitoOptions' => $this->indicatorAmbitoOptions(),
        ]);
    }

    public function enterSortMode(): void
    {
        $this->authorizeCatalogSort();

        $this->normalizeIndicatorOrders();
        $this->sortMode = true;
        $this->search = '';
        $this->resetPage();
        $this->js('window.initMetasIndicatorCatalogSort && window.initMetasIndicatorCatalogSort()');
    }

    public function exitSortMode(): void
    {
        $this->authorizeCatalogSort();

        $this->sortMode = false;
        $this->resetPage();
    }

    /**
     * @param  array<int, int|string>  $orderedIds
     */
    public function updateIndicatorOrder(array $orderedIds): void
    {
        $this->authorizeCatalogSort();

        $orderedIds = array_values(array_map(intval(...), $orderedIds));

        if ($orderedIds === []) {
            return;
        }

        $scopeIds = $this->catalogIndicatorsQuery()
            ->orderBy('orden')
            ->orderBy('clave')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $sortedPayload = $orderedIds;
        sort($sortedPayload);
        $sortedScope = $scopeIds;
        sort($sortedScope);

        if ($sortedPayload !== $sortedScope) {
            abort(422);
        }

        DB::transaction(function () use ($orderedIds): void {
            foreach ($orderedIds as $index => $id) {
                MetIndicator::query()
                    ->where('id', $id)
                    ->where('indicator_type_id', $this->indicatorTypeId)
                    ->where('year_id', $this->yearId)
                    ->update(['orden' => $index + 1]);
            }
        });

        session()->flash('catalog_message', 'Orden de indicadores actualizado.');
        $this->dispatch('catalog-sort-saved');
    }

    private function indicatorAmbitoOptions(): Collection
    {
        return $this->cachedAmbitoOptions ??= app(IndicatorAmbitoAssignmentService::class)->activeCatalog();
    }

    private function authUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function currentIndicatorYear(): Year
    {
        $year = session()->get('indicator_year');
        abort_unless($year instanceof Year, 500);

        return $year;
    }

    private function authorizeCatalogSort(): void
    {
        if (! $this->authUser()->hasRole(['parametrizador', 'admin'])) {
            abort(403);
        }
    }

    private function catalogIndicatorsQuery(): Builder
    {
        $query = MetIndicator::query()
            ->where('indicator_type_id', $this->indicatorTypeId)
            ->where('year_id', $this->yearId);

        if (! $this->authUser()->hasRole(['parametrizador', 'admin'])) {
            $query->where('estado', true);
        }

        return $query;
    }

    private function normalizeIndicatorOrders(): void
    {
        $indicators = MetIndicator::query()
            ->where('indicator_type_id', $this->indicatorTypeId)
            ->where('year_id', $this->yearId)
            ->orderBy('orden')
            ->orderBy('clave')
            ->get();

        foreach ($indicators as $index => $indicator) {
            $expected = $index + 1;

            if ((int) $indicator->orden !== $expected) {
                $indicator->update(['orden' => $expected]);
            }
        }
    }
}
