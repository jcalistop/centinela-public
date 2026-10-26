<?php

namespace App\Livewire\MetasInstrument;

use App\Enums\Cen\CenCenterAdmDependency;
use App\Enums\IndicatorType\EtapaVigente;
use App\Enums\Metas\MetIndicatorPriority;
use App\Livewire\MetasInstrument\Concerns\ManagesEstablishmentResponsibles;
use App\Livewire\MetasInstrument\Concerns\ResolvesInstrumentIndicatorTypeId;
use App\Models\CenCenter;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeGlobalSetting;
use App\Models\IndicatorTypeSetting;
use App\Models\MetCenterAdosado;
use App\Models\MetCenterInclusion;
use App\Models\MetEstablishmentResult;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetMeasurementUnit;
use App\Models\User;
use App\Models\Year;
use App\Services\Metas\MetEstablishmentAgreementService;
use App\Services\Metas\MetEstablishmentIndicatorConfigService;
use App\Services\Metas\MetEstablishmentResponsibleAccess;
use App\Services\Metas\MetIndicatorPeriodicityResolver;
use App\Services\Metas\MetRecalculationDispatcher;
use App\Services\Metas\MetRecalculationScope;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

class CenterSelectionComponent extends Component
{
    use ManagesEstablishmentResponsibles;
    use ResolvesInstrumentIndicatorTypeId;

    /** @var array<int, bool> IDs de centros actualmente incluidos */
    public array $includedCenterIds = [];

    /** @var int|null Centro pendiente de excluir que ya tiene resultados calculados */
    public ?int $pendingRemoveCenterId = null;

    public int $indicatorTypeId;

    public string $instrumentNombre = '';

    /** Vista dedicada de indicadores por establecimiento (SDD-203); no modal. */
    public bool $standaloneConfigPage = false;

    public string $indicatorTypeRoute = '';

    // Modal configuración de indicadores por establecimiento (tests / legacy openConfigModal)
    public bool $showConfigModal = false;

    public ?int $configCenterId = null;

    public string $configCenterName = '';

    public ?int $configMeasurementUnitId = null;

    /** @var array<int, array{seleccionado: bool, ponderacion: string, meta_efectiva: string}> */
    public array $configs = [];

    // Panel de gestión de adosados
    public bool $showAdosadoPanel = false;

    public ?int $adosadoPanelCenterId = null;

    public string $adosadoPanelCenterName = '';

    /** @var array<int, array{id: int, nombre: string, codigo: string}> */
    public array $currentAdosados = [];

    public ?int $selectedAdosadoToAdd = null;

    public string $reopenMotivo = '';

    public bool $showReopenForm = false;

    // Panel de gestión de unidades de desempeño (solo internal_unit, Ley 20.707)
    public bool $showUnitsPanel = false;

    public ?int $unitsPanelCenterId = null;

    public string $unitsPanelCenterName = '';

    /**
     * Cache Livewire de issues de config (fingerprint → payload) — SDD-170.
     * Persiste entre round-trips; invalidar en toggleCenter / saveConfig / mutaciones.
     */
    public ?string $configIssuesFingerprint = null;

    /** @var array<int|string, array{message: string, count?: int}|mixed> */
    public array $configIssuesPayload = [];

    public function mount(
        ?int $indicatorTypeId = null,
        string $instrumentNombre = '',
        bool $standaloneConfigPage = false,
        ?int $configCenterId = null,
    ): void {
        $user = $this->authenticatedUser();
        $this->indicatorTypeId = $this->resolveInstrumentIndicatorTypeId($indicatorTypeId);

        $canAccess = $user->hasRole(['control-gestion', 'admin']);

        if ($standaloneConfigPage && $configCenterId !== null && ! $canAccess) {
            $canAccess = app(MetEstablishmentResponsibleAccess::class)->canViewEstablishmentIndicators(
                $user,
                $configCenterId,
                $this->indicatorYear()->id,
                $this->indicatorTypeId,
            );
        }

        if (! $canAccess) {
            abort(403);
        }

        $type = IndicatorType::query()->findOrFail($this->indicatorTypeId);
        $this->indicatorTypeRoute = (string) $type->route;

        $this->instrumentNombre = $instrumentNombre !== ''
            ? $instrumentNombre
            : (string) ($type->nombre_indicador ?? '');

        $this->loadIncludedCenters();

        if (! $this->standaloneConfigPage) {
            $this->loadDesigneesByCenter();
        }

        if ($standaloneConfigPage) {
            abort_unless($configCenterId !== null, 404);

            $center = CenCenter::query()->findOrFail($configCenterId);

            if (
                ! $this->authenticatedUser()->hasRole('admin')
                && (int) $center->cen_hie_dependency_id !== (int) $this->authenticatedUser()->cen_hie_dependency_id
            ) {
                abort(403);
            }

            if (! isset($this->includedCenterIds[$center->id]) || ! $this->includedCenterIds[$center->id]) {
                abort(404);
            }

            $this->standaloneConfigPage = true;
            $this->openConfigModal($center->id, (string) $center->nombre_corto, skipAuthorizationCheck: true);
        }
    }

    public function render(): View
    {
        $depId = $this->authenticatedUser()->cen_hie_dependency_id;
        $yearId = $this->indicatorYear()->id;

        $usesInternalUnitMeasurement = IndicatorType::query()
            ->whereKey($this->indicatorTypeId)
            ->value('measurement_granularity') === 'internal_unit';

        $setting = IndicatorTypeSetting::getData($this->indicatorTypeId);
        $isCerrado = $setting && ! $setting->etapa_vigente->permiteConfiguracionEstructural();

        if ($this->standaloneConfigPage) {
            return $this->renderStandaloneConfigPage(
                $depId,
                $yearId,
                $usesInternalUnitMeasurement,
                $isCerrado,
            );
        }

        // Para internal_unit los adosados no aplican; se reemplazan por unidades de desempeño
        $adosadoCenterIds = collect();
        $availableCentersForAdosado = collect();
        $adosadoCountsByCenterId = collect();
        $adosadoNamesByCenterId = collect();

        if (! $usesInternalUnitMeasurement) {
            $adosadosContext = $this->loadAdosadosContext($yearId, (int) $depId);
            $adosadoCenterIds = $adosadosContext['adosadoCenterIds'];
            $adosadoCountsByCenterId = $adosadosContext['countsByMadreId'];
            $adosadoNamesByCenterId = $adosadosContext['namesByMadreId'];

            if ($this->showAdosadoPanel && $this->adosadoPanelCenterId !== null) {
                $availableCentersForAdosado = CenCenter::query()
                    ->eligibleForNewConfiguration()
                    ->where('cen_hie_dependency_id', $depId)
                    ->where('cen_adm_dependency', CenCenterAdmDependency::Case1->value)
                    ->whereNotIn('id', array_keys($this->includedCenterIds))
                    ->whereNotIn('id', $adosadoCenterIds->all() ?: [0])
                    ->where('id', '!=', $this->adosadoPanelCenterId)
                    ->orderBy('nombre_corto')
                    ->get(['id', 'nombre_corto', 'codigo']);
            }
        }

        $centers = CenCenter::query()
            ->eligibleForNewConfiguration()
            ->where('cen_hie_dependency_id', $depId)
            ->whereNull('codigo_madre')
            ->where('cen_adm_dependency', CenCenterAdmDependency::Case1->value)
            ->whereNotIn('id', $adosadoCenterIds->all() ?: [0])
            ->when(
                $usesInternalUnitMeasurement,
                fn ($q) => $q,
                fn ($q) => $q->with(['adosados' => fn ($q) => $q->where('cen_hie_dependency_id', $depId)->orderBy('nombre_corto')])
            )
            ->orderBy('nombre_corto')
            ->get();

        // Conteo de unidades por centro (solo internal_unit)
        $unitCountsByCenterId = $usesInternalUnitMeasurement
            ? MetMeasurementUnit::query()
                ->whereIn('cen_center_id', array_keys($this->includedCenterIds) ?: [0])
                ->where('year_id', $yearId)
                ->selectRaw('cen_center_id, COUNT(*) as total')
                ->groupBy('cen_center_id')
                ->pluck('total', 'cen_center_id')
            : collect();

        $includedCenterIds = array_keys(array_filter(
            $this->includedCenterIds,
            fn (bool $included): bool => $included,
        ));
        sort($includedCenterIds);

        $configIssuesByCenterId = $this->resolveConfigIssuesByCenterId(
            $includedCenterIds,
            $yearId,
            (int) $depId,
            $usesInternalUnitMeasurement,
        );

        $indicatorTypeRoute = $this->indicatorTypeRoute;
        $configIndicators = collect();
        $periodicidadPorIndicadorId = [];
        $measurementUnitsForConfigModal = collect();
        $configUnitStatusesForModal = collect();
        $activeConfigUnitCodigo = null;
        $activeConfigUnitNombre = null;
        $configColumnAllAplica = false;
        $configColumnAllAgrupaSs = false;
        $configColumnAllConvenido = false;
        $configHasAnyAplica = false;
        $configMinIndicadores = (int) IndicatorTypeGlobalSetting::getData($this->indicatorTypeId)->min_indicadores;
        $canDesignateResponsibles = $this->canDesignateEstablishmentResponsibles() && ! $isCerrado;
        $referenteUsers = $canDesignateResponsibles ? $this->referenteUsersForDesignation() : collect();

        return view('livewire.metas-instrument.center-selection', compact(
            'centers',
            'isCerrado',
            'configIndicators',
            'periodicidadPorIndicadorId',
            'availableCentersForAdosado',
            'adosadoCountsByCenterId',
            'adosadoNamesByCenterId',
            'measurementUnitsForConfigModal',
            'usesInternalUnitMeasurement',
            'unitCountsByCenterId',
            'configIssuesByCenterId',
            'configUnitStatusesForModal',
            'activeConfigUnitCodigo',
            'activeConfigUnitNombre',
            'configColumnAllAplica',
            'configColumnAllAgrupaSs',
            'configColumnAllConvenido',
            'configHasAnyAplica',
            'configMinIndicadores',
            'indicatorTypeRoute',
            'canDesignateResponsibles',
            'referenteUsers',
        ));
    }

    private function renderStandaloneConfigPage(
        int|string $depId,
        int $yearId,
        bool $usesInternalUnitMeasurement,
        bool $isCerrado,
    ): View {
        $configIndicators = MetIndicator::query()
            ->where('indicator_type_id', $this->indicatorTypeId)
            ->where('year_id', $yearId)
            ->where('estado', true)
            ->with('dataFields')
            ->orderBy('orden')
            ->orderBy('clave')
            ->get();

        $measurementUnitsForConfigModal = collect();
        if ($usesInternalUnitMeasurement && $this->configCenterId) {
            $measurementUnitsForConfigModal = MetMeasurementUnit::query()
                ->where('cen_center_id', $this->configCenterId)
                ->where('year_id', $yearId)
                ->where('cen_hie_dependency_id', $depId)
                ->with('unitType')
                ->orderBy('codigo')
                ->get();
        }

        $periodicidadPorIndicadorId = [];
        if ($configIndicators->isNotEmpty()) {
            $periodicityResolver = app(MetIndicatorPeriodicityResolver::class);

            foreach ($configIndicators as $indicator) {
                $periodicidadPorIndicadorId[$indicator->id] = $periodicityResolver->resolveForCarga(
                    $indicator,
                    $depId,
                    $yearId,
                )->value;
            }
        }

        $configUnitStatusesForModal = collect();
        $activeConfigUnitCodigo = null;
        $activeConfigUnitNombre = null;

        if ($usesInternalUnitMeasurement && $this->configCenterId) {
            $configUnitStatusesForModal = app(MetEstablishmentIndicatorConfigService::class)->unitStatusesForCenter(
                $this->indicatorTypeId,
                $this->configCenterId,
                $yearId,
                (int) $depId,
            );

            if ($this->configMeasurementUnitId) {
                $activeUnit = $configUnitStatusesForModal->firstWhere('id', $this->configMeasurementUnitId);
                $activeConfigUnitCodigo = $activeUnit['codigo'] ?? null;
                $activeConfigUnitNombre = $activeUnit['nombre'] ?? null;
            }
        }

        $configColumnAllAplica = $this->configColumnAllAplica();
        $configColumnAllAgrupaSs = $this->configColumnAllAgrupaSs();
        $configColumnAllConvenido = $this->configColumnAllConvenido();
        $configHasAnyAplica = $this->configHasAnyAplica();
        $configMinIndicadores = (int) IndicatorTypeGlobalSetting::getData($this->indicatorTypeId)->min_indicadores;
        $indicatorTypeRoute = $this->indicatorTypeRoute;
        $ponderacionesOnlyMode = $this->isPonderacionesOnlyMode();
        $canEditFullConfig = $this->canEditFullConfigModal();
        $canEditConvenio = $this->canEditConfigModal();

        $configSelectedCount = $this->configSelectedCount();
        $configPonderacionSum = $this->configPonderacionSum();
        $configSelectedCountOk = $this->configSelectedCountOk($configMinIndicadores);
        $configPonderacionSumOk = $this->configPonderacionSumOk();

        $agreementService = app(MetEstablishmentAgreementService::class);
        $agreement = $this->configCenterId
            ? $agreementService->find(
                (int) $this->configCenterId,
                $yearId,
                $this->indicatorTypeId,
            )
            : null;
        if ($agreement !== null) {
            $agreement->loadMissing('submittedBy.role');
        }
        $isConvenioEnviado = $agreement?->isEnviado() ?? false;
        $etapaSetting = IndicatorTypeSetting::getData($this->indicatorTypeId);
        $canReopenAgreement = $isConvenioEnviado
            && $this->authenticatedUser()->hasRole(['control-gestion', 'admin'])
            && $etapaSetting
            && $etapaSetting->etapa_vigente->permiteFormulacionConvenio();
        $incompleteUnitsForSubmit = [];
        if ($usesInternalUnitMeasurement && $this->configCenterId && ! $isConvenioEnviado) {
            $incompleteUnitsForSubmit = $agreementService->incompleteUnitsForSubmit(
                (int) $this->configCenterId,
                $yearId,
                $this->indicatorTypeId,
                (int) $depId,
                true,
            );
        }

        if ($isConvenioEnviado) {
            $isCerrado = true;
            $canEditFullConfig = false;
            $canEditConvenio = false;
        }

        return view('livewire.metas-instrument.establishment-indicators-config', compact(
            'isCerrado',
            'configIndicators',
            'periodicidadPorIndicadorId',
            'measurementUnitsForConfigModal',
            'usesInternalUnitMeasurement',
            'configUnitStatusesForModal',
            'activeConfigUnitCodigo',
            'activeConfigUnitNombre',
            'configColumnAllAplica',
            'configColumnAllAgrupaSs',
            'configColumnAllConvenido',
            'configHasAnyAplica',
            'configMinIndicadores',
            'indicatorTypeRoute',
            'ponderacionesOnlyMode',
            'canEditFullConfig',
            'canEditConvenio',
            'configSelectedCount',
            'configPonderacionSum',
            'configSelectedCountOk',
            'configPonderacionSumOk',
            'agreement',
            'isConvenioEnviado',
            'canReopenAgreement',
            'incompleteUnitsForSubmit',
        ));
    }

    public function toggleCenter(int $centerId): void
    {
        if (! $this->authenticatedUser()->hasRole(['control-gestion', 'admin'])) {
            abort(403);
        }

        $setting = IndicatorTypeSetting::getData($this->indicatorTypeId);

        if ($setting && ! $setting->etapa_vigente->permiteConfiguracionEstructural()) {
            $this->addError('general', $this->configStructuralBlockedMessage($setting->etapa_vigente));

            return;
        }

        $yearId = $this->indicatorYear()->id;
        $depId = $this->authenticatedUser()->cen_hie_dependency_id;

        $isIncluded = isset($this->includedCenterIds[$centerId]) && $this->includedCenterIds[$centerId];

        if (! $isIncluded) {
            $esAdosado = $this->adosadosForCurrentInstrument()
                ->where('cen_center_adosado_id', $centerId)
                ->where('year_id', $yearId)
                ->where('cen_hie_dependency_id', $depId)
                ->exists();

            if ($esAdosado) {
                $this->addError('general', 'Este establecimiento es un adosado y no puede ser incluido como centro madre.');

                return;
            }
        }

        if ($isIncluded) {
            // Verificar si hay resultados calculados antes de excluir
            $hasResults = MetEstablishmentResult::query()
                ->where('cen_center_id', $centerId)
                ->where('year_id', $yearId)
                ->where('indicator_type_id', $this->indicatorTypeId)
                ->whereNotNull('resultado_global')
                ->exists();

            if ($hasResults && $this->pendingRemoveCenterId !== $centerId) {
                // Primera vez: pedir confirmación
                $this->pendingRemoveCenterId = $centerId;

                return;
            }

            // Confirmado o sin resultados: eliminar inclusión
            MetCenterInclusion::query()
                ->where('cen_center_id', $centerId)
                ->where('year_id', $yearId)
                ->where('cen_hie_dependency_id', $depId)
                ->where('indicator_type_id', $this->indicatorTypeId)
                ->delete();

            unset($this->includedCenterIds[$centerId]);
            $this->pendingRemoveCenterId = null;
        } else {
            MetCenterInclusion::firstOrCreate(
                [
                    'cen_center_id' => $centerId,
                    'year_id' => $yearId,
                    'indicator_type_id' => $this->indicatorTypeId,
                ],
                [
                    'cen_hie_dependency_id' => $depId,
                ]
            );

            $this->includedCenterIds[$centerId] = true;
            $this->pendingRemoveCenterId = null;
        }

        $this->forgetConfigIssuesCache();
    }

    public function cancelRemove(): void
    {
        $this->pendingRemoveCenterId = null;
    }

    public function openAdosadoPanel(int $centerId, string $centerName): void
    {
        if (! $this->authenticatedUser()->hasRole(['control-gestion', 'admin'])) {
            abort(403);
        }

        $this->adosadoPanelCenterId = $centerId;
        $this->adosadoPanelCenterName = $centerName;
        $this->selectedAdosadoToAdd = null;
        $this->loadAdosados();
        $this->showAdosadoPanel = true;
    }

    public function closeAdosadoPanel(): void
    {
        $this->showAdosadoPanel = false;
        $this->adosadoPanelCenterId = null;
        $this->adosadoPanelCenterName = '';
        $this->currentAdosados = [];
        $this->selectedAdosadoToAdd = null;
        $this->resetErrorBag('adosado');
    }

    public function openUnitsPanel(int $centerId, string $centerName): void
    {
        if (! $this->authenticatedUser()->hasRole(['control-gestion', 'admin'])) {
            abort(403);
        }

        $this->assertInternalUnitInstrument();

        $this->unitsPanelCenterId = $centerId;
        $this->unitsPanelCenterName = $centerName;
        $this->showUnitsPanel = true;
    }

    public function closeUnitsPanel(): void
    {
        $this->showUnitsPanel = false;
        $this->unitsPanelCenterId = null;
        $this->unitsPanelCenterName = '';
    }

    #[On('units-updated')]
    public function onUnitsUpdated(): void
    {
        $this->forgetConfigIssuesCache();
    }

    public function addAdosado(): void
    {
        if (! $this->authenticatedUser()->hasRole(['control-gestion', 'admin'])) {
            abort(403);
        }

        $setting = IndicatorTypeSetting::getData($this->indicatorTypeId);

        if ($setting && ! $setting->etapa_vigente->permiteConfiguracionEstructural()) {
            $this->addError('adosado', $this->configStructuralBlockedMessage($setting->etapa_vigente));

            return;
        }

        if ($this->selectedAdosadoToAdd === null) {
            $this->addError('adosado', 'Debe seleccionar un establecimiento para adosar.');

            return;
        }

        $yearId = $this->indicatorYear()->id;
        $depId = $this->authenticatedUser()->cen_hie_dependency_id;
        $adosadoId = (int) $this->selectedAdosadoToAdd;

        // Validar que pertenece al mismo SS
        $adosadoCenter = CenCenter::query()
            ->where('id', $adosadoId)
            ->where('cen_hie_dependency_id', $depId)
            ->first();

        if ($adosadoCenter === null) {
            $this->addError('adosado', 'El establecimiento seleccionado no pertenece a su Servicio de Salud.');

            return;
        }

        // Validar que no sea una madre incluida
        $isMadreIncluida = MetCenterInclusion::query()
            ->where('cen_center_id', $adosadoId)
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $depId)
            ->where('indicator_type_id', $this->indicatorTypeId)
            ->exists();

        if ($isMadreIncluida) {
            $this->addError('adosado', 'Este establecimiento está incluido como centro madre y no puede ser adosado.');

            return;
        }

        // Validar que no esté adosado a otra madre en el mismo año
        $yaEsAdosado = $this->adosadosForCurrentInstrument()
            ->where('cen_center_adosado_id', $adosadoId)
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $depId)
            ->exists();

        if ($yaEsAdosado) {
            $this->addError('adosado', 'Este establecimiento ya está adosado a otro centro madre en este instrumento.');

            return;
        }

        MetCenterAdosado::create([
            'cen_center_madre_id' => $this->adosadoPanelCenterId,
            'cen_center_adosado_id' => $adosadoId,
            'year_id' => $yearId,
            'indicator_type_id' => $this->indicatorTypeId,
            'cen_hie_dependency_id' => $depId,
        ]);

        $this->selectedAdosadoToAdd = null;
        $this->resetErrorBag('adosado');
        $this->loadAdosados();
        $this->dispatchAdosadosRecalculation();
    }

    public function removeAdosado(int $adosadoId): void
    {
        if (! $this->authenticatedUser()->hasRole(['control-gestion', 'admin'])) {
            abort(403);
        }

        $setting = IndicatorTypeSetting::getData($this->indicatorTypeId);

        if ($setting && ! $setting->etapa_vigente->permiteConfiguracionEstructural()) {
            $this->addError('adosado', $this->configStructuralBlockedMessage($setting->etapa_vigente));

            return;
        }

        $yearId = $this->indicatorYear()->id;
        $depId = $this->authenticatedUser()->cen_hie_dependency_id;

        $this->adosadosForCurrentInstrument()
            ->where('cen_center_madre_id', $this->adosadoPanelCenterId)
            ->where('cen_center_adosado_id', $adosadoId)
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $depId)
            ->delete();

        $this->loadAdosados();
        $this->dispatchAdosadosRecalculation();
    }

    public function loadDefaultAdosados(): void
    {
        if (! $this->authenticatedUser()->hasRole(['control-gestion', 'admin'])) {
            abort(403);
        }

        $setting = IndicatorTypeSetting::getData($this->indicatorTypeId);

        if ($setting && ! $setting->etapa_vigente->permiteConfiguracionEstructural()) {
            $this->addError('adosado', $this->configStructuralBlockedMessage($setting->etapa_vigente));

            return;
        }

        $yearId = $this->indicatorYear()->id;
        $depId = $this->authenticatedUser()->cen_hie_dependency_id;

        $madre = CenCenter::find($this->adosadoPanelCenterId);

        if ($madre === null) {
            return;
        }

        // Centros que tienen codigo_madre = madre.codigo en el mismo SS con dependencia administrativa SS
        $candidatos = CenCenter::query()
            ->eligibleForNewConfiguration()
            ->where('cen_hie_dependency_id', $depId)
            ->where('cen_adm_dependency', CenCenterAdmDependency::Case1->value)
            ->where('codigo_madre', $madre->codigo)
            ->get(['id']);

        $inserted = 0;

        foreach ($candidatos as $candidato) {
            // Respetar regla: no adosar una madre incluida
            $isMadreIncluida = MetCenterInclusion::query()
                ->where('cen_center_id', $candidato->id)
                ->where('year_id', $yearId)
                ->where('cen_hie_dependency_id', $depId)
                ->where('indicator_type_id', $this->indicatorTypeId)
                ->exists();

            if ($isMadreIncluida) {
                continue;
            }

            // Ignorar si ya es adosado de otra madre (unique constraint protege, pero evitamos error)
            $yaEsAdosado = $this->adosadosForCurrentInstrument()
                ->where('cen_center_adosado_id', $candidato->id)
                ->where('year_id', $yearId)
                ->where('cen_hie_dependency_id', $depId)
                ->exists();

            if ($yaEsAdosado) {
                continue;
            }

            MetCenterAdosado::firstOrCreate(
                [
                    'cen_center_madre_id' => $this->adosadoPanelCenterId,
                    'cen_center_adosado_id' => $candidato->id,
                    'year_id' => $yearId,
                    'indicator_type_id' => $this->indicatorTypeId,
                ],
                ['cen_hie_dependency_id' => $depId]
            );

            $inserted++;
        }

        $this->resetErrorBag('adosado');
        $this->loadAdosados();

        if ($inserted === 0) {
            session()->flash('adosado_info', 'No se encontraron adosados por defecto nuevos para cargar.');
        } else {
            session()->flash('adosado_info', "Se cargaron {$inserted} adosado(s) por defecto.");
            $this->dispatchAdosadosRecalculation();
        }
    }

    public function openConfigModal(int $centerId, string $centerName, bool $skipAuthorizationCheck = false): void
    {
        $user = $this->authenticatedUser();

        if (! $skipAuthorizationCheck && ! $user->hasRole(['control-gestion', 'admin'])) {
            abort(403);
        }

        // Vista standalone: basta poder consultar el convenio (también ENVIADO / solo lectura).
        if (
            $skipAuthorizationCheck
            && ! app(MetEstablishmentResponsibleAccess::class)->canViewEstablishmentIndicators(
                $user,
                $centerId,
                $this->indicatorYear()->id,
                $this->indicatorTypeId,
            )
        ) {
            abort(403);
        }

        $this->configCenterId = $centerId;
        $this->configCenterName = $centerName;
        $this->configs = [];
        $this->configMeasurementUnitId = null;

        $type = IndicatorType::query()->findOrFail($this->indicatorTypeId);

        if ($type->usesInternalUnitMeasurement()) {
            $firstUnitId = MetMeasurementUnit::query()
                ->where('cen_center_id', $centerId)
                ->where('year_id', $this->indicatorYear()->id)
                ->where('cen_hie_dependency_id', $this->authenticatedUser()->cen_hie_dependency_id)
                ->orderBy('codigo')
                ->value('id');

            $this->configMeasurementUnitId = $firstUnitId ? (int) $firstUnitId : null;
        }

        $this->loadConfigs();
        $this->showConfigModal = true;
    }

    public function closeConfigModal(): void
    {
        if ($this->standaloneConfigPage) {
            $this->redirectAfterStandaloneConfig();

            return;
        }

        $this->showConfigModal = false;
        $this->configCenterId = null;
        $this->configCenterName = '';
        $this->configMeasurementUnitId = null;
        $this->configs = [];
        $this->resetErrorBag();
    }

    public function updatedConfigMeasurementUnitId(): void
    {
        $this->configs = [];
        $this->loadConfigs();
    }

    public function selectConfigMeasurementUnit(int $unitId): void
    {
        if ($this->configMeasurementUnitId === $unitId) {
            return;
        }

        $this->configMeasurementUnitId = $unitId;
        $this->updatedConfigMeasurementUnitId();
    }

    public function updatedConfigs(mixed $value, ?string $key): void
    {
        if ($this->isPonderacionesOnlyMode()) {
            return;
        }

        if ($key === null || ! str_ends_with($key, '.aplica')) {
            return;
        }

        if (! (bool) $value) {
            $indicatorId = (int) explode('.', $key)[0];

            if (isset($this->configs[$indicatorId])) {
                $this->configs[$indicatorId]['agrupa_ss'] = false;
                $this->configs[$indicatorId]['seleccionado'] = false;
                $this->configs[$indicatorId]['ponderacion'] = '';
                $this->configs[$indicatorId]['meta_efectiva'] = '';
            }
        }

        $this->syncConfigDependentColumnsWhenNoAplica();
    }

    public function toggleAllConfigAplica(): void
    {
        if (! $this->canEditConfigModal()) {
            return;
        }

        $newValue = ! $this->configColumnAllAplica();

        foreach ($this->configs as &$cfg) {
            $cfg['aplica'] = $newValue;

            if (! $newValue) {
                $cfg['agrupa_ss'] = false;
                $cfg['seleccionado'] = false;
                $cfg['ponderacion'] = '';
                $cfg['meta_efectiva'] = '';
            }
        }
        unset($cfg);

        $this->syncConfigDependentColumnsWhenNoAplica();
    }

    public function toggleAllConfigAgrupaSs(): void
    {
        if (! $this->canEditConfigModal() || ! $this->configHasAnyAplica()) {
            return;
        }

        $newValue = ! $this->configColumnAllAgrupaSs();

        foreach ($this->configs as &$cfg) {
            if (! (bool) ($cfg['aplica'] ?? false)) {
                continue;
            }

            $cfg['agrupa_ss'] = $newValue;
        }
        unset($cfg);
    }

    public function toggleAllConfigConvenido(): void
    {
        if (! $this->canEditConfigModal() || ! $this->configHasAnyAplica()) {
            return;
        }

        $newValue = ! $this->configColumnAllConvenido();

        foreach ($this->configs as &$cfg) {
            if (! (bool) ($cfg['aplica'] ?? false)) {
                continue;
            }

            $cfg['seleccionado'] = $newValue;

            if (! $newValue) {
                $cfg['ponderacion'] = '';
            }
        }
        unset($cfg);
    }

    public function saveDraft(): void
    {
        if (! $this->persistConfig(requireFullValidation: false)) {
            return;
        }

        session()->flash('config_message', "Borrador guardado para {$this->configCenterName}.");
        $this->forgetConfigIssuesCache();
        $this->finishConfigPersist();
    }

    public function submitToSs(): void
    {
        if (! $this->persistConfig(requireFullValidation: true)) {
            return;
        }

        $user = $this->authenticatedUser();
        $yearId = $this->indicatorYear()->id;

        try {
            app(MetEstablishmentAgreementService::class)->submit(
                (int) $this->configCenterId,
                $yearId,
                $this->indicatorTypeId,
                (int) $user->cen_hie_dependency_id,
                $user,
            );
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'No se pudo enviar el convenio.';
            $this->addConfigGeneralError($message);

            return;
        }

        session()->flash('config_message', "Convenio enviado a SS para {$this->configCenterName}.");
        $this->forgetConfigIssuesCache();
        $this->finishConfigPersist();
    }

    public function reopenAgreement(): void
    {
        $user = $this->authenticatedUser();

        if (! $user->hasRole(['control-gestion', 'admin']) || $this->configCenterId === null) {
            abort(403);
        }

        try {
            app(MetEstablishmentAgreementService::class)->reopen(
                (int) $this->configCenterId,
                $this->indicatorYear()->id,
                $this->indicatorTypeId,
                (int) $user->cen_hie_dependency_id,
                $user,
                $this->reopenMotivo,
            );
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'No se pudo reabrir el convenio.';
            $this->addError('reopenMotivo', $message);

            return;
        }

        $this->reopenMotivo = '';
        $this->showReopenForm = false;
        $this->forgetConfigIssuesCache();
        session()->flash('config_message', "Convenio reabierto para {$this->configCenterName}.");
    }

    public function toggleReopenForm(): void
    {
        $this->showReopenForm = ! $this->showReopenForm;

        if (! $this->showReopenForm) {
            $this->reopenMotivo = '';
            $this->resetErrorBag('reopenMotivo');
        }
    }

    /**
     * Compatibilidad con tests previos a SDD-207: persiste con validación completa sin marcar ENVIADO.
     */
    public function saveConfig(): void
    {
        if (! $this->persistConfig(requireFullValidation: true)) {
            return;
        }

        session()->flash('config_message', "Configuración guardada para {$this->configCenterName}.");
        $this->forgetConfigIssuesCache();
        $this->finishConfigPersist();
    }

    private function finishConfigPersist(): void
    {
        if ($this->standaloneConfigPage) {
            $this->redirectAfterStandaloneConfig();

            return;
        }

        $this->closeConfigModal();
    }

    private function persistConfig(bool $requireFullValidation): bool
    {
        if (! $this->canEditConfigModal()) {
            abort(403);
        }

        $yearId = $this->indicatorYear()->id;

        if ($this->isPonderacionesOnlyMode()) {
            $this->enforceResponsibleConfigRestrictionsFromDatabase($yearId);
        }

        $setting = IndicatorTypeSetting::getData($this->indicatorTypeId);

        if ($setting && ! $setting->etapa_vigente->permiteConfiguracionEstructural() && ! $this->isPonderacionesOnlyMode()) {
            $this->addError('configGeneral', $this->configStructuralBlockedMessage($setting->etapa_vigente));

            return false;
        }

        if ($setting && ! $setting->etapa_vigente->permiteFormulacionConvenio()) {
            $this->enforceConvenioFrozenFromDatabase($yearId);
        }

        foreach ($this->configs as $indicatorId => &$cfg) {
            if (! (bool) ($cfg['aplica'] ?? false)) {
                $cfg['agrupa_ss'] = false;
                $cfg['seleccionado'] = false;
                $cfg['ponderacion'] = '';
            }
        }
        unset($cfg);

        $globalSetting = IndicatorTypeGlobalSetting::getData($this->indicatorTypeId);

        $selectedConfigs = array_filter($this->configs, fn ($c) => (bool) ($c['seleccionado'] ?? false));
        $selectedCount = count($selectedConfigs);

        if ($selectedCount >= 1) {
            $sumPonderacion = array_sum(array_map('floatval', array_column($selectedConfigs, 'ponderacion')));

            foreach ($selectedConfigs as $config) {
                $pond = (float) ($config['ponderacion'] ?? 0);

                if ($pond < (float) $globalSetting->min_ponderacion) {
                    $this->addConfigGeneralError("La ponderación de cada indicador seleccionado debe ser al menos {$globalSetting->min_ponderacion}.");

                    return false;
                }

                if ($pond > (float) $globalSetting->max_ponderacion) {
                    $this->addConfigGeneralError("La ponderación de cada indicador seleccionado no puede superar {$globalSetting->max_ponderacion}.");

                    return false;
                }
            }

            if (! $this->validatePriorityPonderacionesForSelected($selectedConfigs)) {
                return false;
            }

            if ($requireFullValidation) {
                if ($selectedCount < $globalSetting->min_indicadores) {
                    $this->addConfigGeneralError("Debe seleccionar al menos {$globalSetting->min_indicadores} indicadores.");

                    return false;
                }

                if (abs($sumPonderacion - 100) > 0.001) {
                    $this->addConfigGeneralError("La suma de ponderaciones debe ser exactamente 100 (actual: {$sumPonderacion}).");

                    return false;
                }
            }
        }

        $type = IndicatorType::query()->findOrFail($this->indicatorTypeId);

        if ($type->usesInternalUnitMeasurement() && ! $this->configMeasurementUnitId) {
            $this->addConfigGeneralError('Debe seleccionar una unidad de medición para este instrumento.');

            return false;
        }

        foreach ($this->configs as $indicatorId => $config) {
            $seleccionado = (bool) ($config['seleccionado'] ?? false);
            $ponderacion = $seleccionado && $config['ponderacion'] !== '' ? $config['ponderacion'] : null;
            $metaEfectiva = $config['meta_efectiva'] !== '' ? $config['meta_efectiva'] : null;

            $aplica = (bool) ($config['aplica'] ?? false);
            $agrupa_ss = $type->usesInternalUnitMeasurement()
                ? false
                : ($aplica && (bool) ($config['agrupa_ss'] ?? false));

            $matchKeys = [
                'met_indicator_id' => $indicatorId,
                'year_id' => $yearId,
                'cen_center_id' => $this->configCenterId,
            ];

            if ($type->usesInternalUnitMeasurement()) {
                $matchKeys['met_measurement_unit_id'] = $this->configMeasurementUnitId;
            } else {
                $matchKeys['met_measurement_unit_id'] = null;
            }

            MetIndicatorConfiguration::updateOrCreate(
                $matchKeys,
                [
                    'aplica' => $aplica,
                    'agrupa_ss' => $agrupa_ss,
                    'seleccionado' => $seleccionado,
                    'ponderacion' => $ponderacion,
                    'meta_efectiva' => $metaEfectiva,
                ]
            );
        }

        $dependencyId = (int) $this->authenticatedUser()->cen_hie_dependency_id;

        foreach ($this->configs as $indicatorId => $config) {
            if (! (bool) ($config['aplica'] ?? false)) {
                continue;
            }

            app(MetRecalculationDispatcher::class)->scheduleAutoevaluacionScope(
                new MetRecalculationScope(
                    yearId: $yearId,
                    dependencyId: $dependencyId,
                    indicatorTypeId: $this->indicatorTypeId,
                    centerId: $this->configCenterId,
                    metIndicatorId: (int) $indicatorId,
                    measurementUnitId: $type->usesInternalUnitMeasurement()
                        ? $this->configMeasurementUnitId
                        : null,
                ),
            );
        }

        return true;
    }

    #[On('indicator-catalog-updated')]
    public function refreshConfigs(): void
    {
        $this->forgetConfigIssuesCache();

        if ($this->configCenterId) {
            $this->loadConfigs();
        }
    }

    private function loadAdosados(): void
    {
        if ($this->adosadoPanelCenterId === null) {
            $this->currentAdosados = [];

            return;
        }

        $yearId = $this->indicatorYear()->id;
        $depId = $this->authenticatedUser()->cen_hie_dependency_id;

        $this->currentAdosados = $this->adosadosForCurrentInstrument()
            ->where('cen_center_madre_id', $this->adosadoPanelCenterId)
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $depId)
            ->with('centerAdosado:id,nombre_corto,codigo')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->cen_center_adosado_id,
                'nombre' => $a->centerAdosado->nombre_corto ?? '—',
                'codigo' => $a->centerAdosado->codigo ?? '—',
            ])
            ->toArray();
    }

    private function loadConfigs(): void
    {
        $yearId = $this->indicatorYear()->id;

        $indicatorModels = MetIndicator::query()
            ->where('indicator_type_id', $this->indicatorTypeId)
            ->where('year_id', $yearId)
            ->where('estado', true)
            ->get()
            ->keyBy('id');

        foreach ($indicatorModels as $id => $indicator) {
            // Sin fila en BD: Aplica desmarcado (opt-in); la fila existente sigue definiendo el valor real.
            $this->configs[$id] = [
                'aplica' => false,
                'agrupa_ss' => false,
                'seleccionado' => false,
                'ponderacion' => '',
                'meta_efectiva' => $indicator->meta_valor !== null
                    ? number_format((float) $indicator->meta_valor, $indicator->decimales, '.', '')
                    : '',
            ];
        }

        if ($this->configCenterId) {
            $existingQuery = MetIndicatorConfiguration::query()
                ->whereIn('met_indicator_id', $indicatorModels->keys())
                ->where('year_id', $yearId)
                ->where('cen_center_id', $this->configCenterId);

            $instrumentType = IndicatorType::query()->find($this->indicatorTypeId);

            if ($instrumentType?->usesInternalUnitMeasurement()) {
                if ($this->configMeasurementUnitId) {
                    $existingQuery->where('met_measurement_unit_id', $this->configMeasurementUnitId);
                } else {
                    $existingQuery->whereRaw('1 = 0');
                }
            } else {
                $existingQuery->whereNull('met_measurement_unit_id');
            }

            $existing = $existingQuery->get();

            foreach ($existing as $config) {
                $indicatorModel = $indicatorModels->get($config->met_indicator_id);
                $decimals = $indicatorModel ? $indicatorModel->decimales : 4;
                $defaultMeta = optional($indicatorModel)->meta_valor;
                $this->configs[$config->met_indicator_id] = [
                    'aplica' => $config->aplica !== null ? (bool) $config->aplica : true,
                    'agrupa_ss' => (bool) ($config->agrupa_ss ?? false),
                    'seleccionado' => (bool) $config->seleccionado,
                    'ponderacion' => $config->ponderacion !== null ? (string) $config->ponderacion : '',
                    'meta_efectiva' => $config->meta_efectiva !== null
                        ? number_format((float) $config->meta_efectiva, $decimals, '.', '')
                        : ($defaultMeta !== null ? number_format((float) $defaultMeta, $decimals, '.', '') : ''),
                ];
            }
        }
    }

    protected function shouldManageEstablishmentResponsibles(): bool
    {
        return ! $this->standaloneConfigPage;
    }

    private function authenticatedUser(): User
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }

    private function assertInternalUnitInstrument(): void
    {
        $type = IndicatorType::query()->findOrFail($this->indicatorTypeId);
        abort_unless($type->usesInternalUnitMeasurement(), 404);
    }

    private function indicatorYear(): Year
    {
        $year = session('indicator_year');
        if (! $year instanceof Year) {
            abort(500, 'Año indicador no configurado en sesión.');
        }

        return $year;
    }

    private function adosadosForCurrentInstrument(): Builder
    {
        return MetCenterAdosado::query()->forInstrument($this->indicatorTypeId);
    }

    /**
     * Una sola query de adosados del instrumento × año × SS; proyecciones en memoria (SDD-170).
     *
     * @return array{
     *     adosadoCenterIds: Collection<int, int|string>,
     *     countsByMadreId: Collection<int|string, int>,
     *     namesByMadreId: Collection<int|string, Collection<int, string>>
     * }
     */
    private function loadAdosadosContext(int $yearId, int $depId): array
    {
        $rows = $this->adosadosForCurrentInstrument()
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $depId)
            ->with('centerAdosado:id,nombre_corto')
            ->get();

        $madreIds = array_keys($this->includedCenterIds);
        $forIncluded = $madreIds === []
            ? collect()
            : $rows->whereIn('cen_center_madre_id', $madreIds);

        return [
            'adosadoCenterIds' => $rows->pluck('cen_center_adosado_id'),
            'countsByMadreId' => $forIncluded
                ->groupBy('cen_center_madre_id')
                ->map(fn (Collection $group): int => $group->count()),
            'namesByMadreId' => $forIncluded
                ->groupBy('cen_center_madre_id')
                ->map(
                    fn (Collection $group): Collection => $group
                        ->map(fn ($row) => optional($row->centerAdosado)->nombre_corto)
                        ->filter()
                        ->values()
                ),
        ];
    }

    /**
     * @param  list<int>  $includedCenterIds
     */
    private function resolveConfigIssuesByCenterId(
        array $includedCenterIds,
        int $yearId,
        int $depId,
        bool $usesInternalUnitMeasurement,
    ): Collection {
        $fingerprint = implode('|', [
            $this->indicatorTypeId,
            $yearId,
            $depId,
            $usesInternalUnitMeasurement ? '1' : '0',
            implode(',', $includedCenterIds),
        ]);

        if ($this->configIssuesFingerprint === $fingerprint) {
            return collect($this->configIssuesPayload);
        }

        $issues = app(MetEstablishmentIndicatorConfigService::class)->issuesByCenterId(
            $this->indicatorTypeId,
            $includedCenterIds,
            $yearId,
            $depId,
            $usesInternalUnitMeasurement,
        );

        $this->configIssuesFingerprint = $fingerprint;
        $this->configIssuesPayload = $issues->all();

        return $issues;
    }

    private function forgetConfigIssuesCache(): void
    {
        $this->configIssuesFingerprint = null;
        $this->configIssuesPayload = [];
    }

    private function dispatchAdosadosRecalculation(): void
    {
        app(MetRecalculationDispatcher::class)->scheduleFullAutoevaluacion(
            $this->indicatorYear()->id,
            (int) $this->authenticatedUser()->cen_hie_dependency_id,
            $this->indicatorTypeId,
        );
    }

    private function loadIncludedCenters(): void
    {
        $yearId = $this->indicatorYear()->id;
        $depId = $this->authenticatedUser()->cen_hie_dependency_id;

        $included = MetCenterInclusion::query()
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $depId)
            ->where('indicator_type_id', $this->indicatorTypeId)
            ->pluck('cen_center_id');

        $this->includedCenterIds = $included->mapWithKeys(fn ($id) => [$id => true])->toArray();
    }

    private function redirectAfterStandaloneConfig(): void
    {
        $user = $this->authenticatedUser();

        if ($user->hasRole(['control-gestion', 'admin'])) {
            $this->redirect(route('metas.instrument.establishments.configure', [
                'indicatorType' => $this->indicatorTypeRoute,
            ]));

            return;
        }

        // Responsable: la lista de establecimientos es solo CG/admin (403).
        // Tras guardar o volver, regresa a resultados del centro o al hub del instrumento.
        if ($this->configCenterId !== null) {
            $this->redirect(route('metas.instrument.establishment.results', [
                'indicatorType' => $this->indicatorTypeRoute,
                'center' => $this->configCenterId,
            ]));

            return;
        }

        $this->redirect(route('metas.instrument.index', [
            'indicatorType' => $this->indicatorTypeRoute,
        ]));
    }

    private function canEditConfigModal(): bool
    {
        if (
            $this->configCenterId !== null
            && ! app(MetEstablishmentAgreementService::class)->isEditable(
                $this->configCenterId,
                $this->indicatorYear()->id,
                $this->indicatorTypeId,
            )
        ) {
            return false;
        }

        $setting = IndicatorTypeSetting::getData($this->indicatorTypeId);
        $user = $this->authenticatedUser();

        if ($user->hasRole(['control-gestion', 'admin'])) {
            return $setting === null || $setting->etapa_vigente->permiteConfiguracionEstructural();
        }

        if ($this->configCenterId === null) {
            return false;
        }

        if ($setting && ! $setting->etapa_vigente->permiteFormulacionConvenio()) {
            return false;
        }

        return app(MetEstablishmentResponsibleAccess::class)->canEditPonderaciones(
            $user,
            $this->configCenterId,
            $this->indicatorYear()->id,
            $this->indicatorTypeId,
        );
    }

    private function canEditFullConfigModal(): bool
    {
        $setting = IndicatorTypeSetting::getData($this->indicatorTypeId);

        if ($setting && ! $setting->etapa_vigente->permiteConfiguracionEstructural()) {
            return false;
        }

        return $this->authenticatedUser()->hasRole(['control-gestion', 'admin']);
    }

    private function isPonderacionesOnlyMode(): bool
    {
        return $this->canEditConfigModal() && ! $this->canEditFullConfigModal();
    }

    private function enforceConvenioFrozenFromDatabase(int $yearId): void
    {
        $snapshot = $this->configsFromDatabase($yearId);

        foreach ($this->configs as $indicatorId => $submitted) {
            $stored = $snapshot[$indicatorId] ?? null;

            if ($stored === null) {
                continue;
            }

            foreach (['seleccionado', 'ponderacion'] as $field) {
                if (($submitted[$field] ?? null) !== ($stored[$field] ?? null)) {
                    abort(403, 'No puede modificar En convenio ni Ponderación fuera de la etapa de Formulación.');
                }
            }

            $this->configs[$indicatorId]['seleccionado'] = $stored['seleccionado'];
            $this->configs[$indicatorId]['ponderacion'] = $stored['ponderacion'];
        }
    }

    private function configStructuralBlockedMessage(EtapaVigente $etapa): string
    {
        return match ($etapa) {
            EtapaVigente::NoIniciado => 'No se puede modificar la configuración: el instrumento aún no ha iniciado su ciclo.',
            EtapaVigente::Cerrado => 'No se puede modificar la configuración: el instrumento está CERRADO.',
            default => 'No se puede modificar la configuración en la etapa vigente: '.$etapa->label().'.',
        };
    }

    private function enforceResponsibleConfigRestrictionsFromDatabase(int $yearId): void
    {
        $snapshot = $this->configsFromDatabase($yearId);

        foreach ($this->configs as $indicatorId => $submitted) {
            $stored = $snapshot[$indicatorId] ?? null;

            if ($stored === null) {
                continue;
            }

            foreach (['aplica', 'agrupa_ss', 'meta_efectiva'] as $field) {
                if (($submitted[$field] ?? null) !== ($stored[$field] ?? null)) {
                    abort(403, 'No puede modificar columnas distintas a En convenio y Ponderación.');
                }
            }

            $this->configs[$indicatorId]['aplica'] = $stored['aplica'];
            $this->configs[$indicatorId]['agrupa_ss'] = $stored['agrupa_ss'];
            $this->configs[$indicatorId]['meta_efectiva'] = $stored['meta_efectiva'];
        }
    }

    /**
     * @return array<int, array{aplica: bool, agrupa_ss: bool, seleccionado: bool, ponderacion: string, meta_efectiva: string}>
     */
    private function configsFromDatabase(int $yearId): array
    {
        $previousConfigs = $this->configs;
        $this->loadConfigs();
        $snapshot = $this->configs;
        $this->configs = $previousConfigs;

        return $snapshot;
    }

    private function configColumnAllAplica(): bool
    {
        if ($this->configs === []) {
            return false;
        }

        foreach ($this->configs as $cfg) {
            if (! (bool) ($cfg['aplica'] ?? false)) {
                return false;
            }
        }

        return true;
    }

    private function configSelectedCount(): int
    {
        return collect($this->configs)
            ->filter(fn (array $config): bool => (bool) ($config['seleccionado'] ?? false))
            ->count();
    }

    private function configPonderacionSum(): float
    {
        return (float) collect($this->configs)
            ->filter(fn (array $config): bool => (bool) ($config['seleccionado'] ?? false))
            ->sum(fn (array $config): float => (float) ($config['ponderacion'] ?? 0));
    }

    private function configSelectedCountOk(int $minIndicadores): bool
    {
        $count = $this->configSelectedCount();

        return $count === 0 || $count >= $minIndicadores;
    }

    private function configPonderacionSumOk(): bool
    {
        $count = $this->configSelectedCount();

        if ($count === 0) {
            return true;
        }

        return abs($this->configPonderacionSum() - 100) < 0.001;
    }

    private function configHasAnyAplica(): bool
    {
        foreach ($this->configs as $cfg) {
            if ((bool) ($cfg['aplica'] ?? false)) {
                return true;
            }
        }

        return false;
    }

    private function syncConfigDependentColumnsWhenNoAplica(): void
    {
        if ($this->configHasAnyAplica()) {
            return;
        }

        foreach ($this->configs as &$cfg) {
            $cfg['agrupa_ss'] = false;
            $cfg['seleccionado'] = false;
            $cfg['ponderacion'] = '';
        }
        unset($cfg);
    }

    /**
     * DR-211-07: en metas-19664, min(ponderación Alta) > max(ponderación no-Alta) entre indicadores en convenio.
     *
     * @param  array<int|string, array{seleccionado?: bool, ponderacion?: string}>  $selectedConfigs
     */
    private function validatePriorityPonderacionesForSelected(array $selectedConfigs): bool
    {
        if ($this->indicatorTypeRoute !== 'metas-19664') {
            return true;
        }

        $indicatorIds = array_map('intval', array_keys($selectedConfigs));
        $priorities = MetIndicator::query()
            ->whereIn('id', $indicatorIds)
            ->get(['id', 'prioridad'])
            ->keyBy('id');

        $altaPonds = [];
        $noAltaPonds = [];

        foreach ($selectedConfigs as $indicatorId => $config) {
            $priority = $priorities->get((int) $indicatorId)?->prioridad ?? MetIndicatorPriority::SinPrioridad;
            $pond = (float) ($config['ponderacion'] ?? 0);

            if ($priority->isAlta()) {
                $altaPonds[] = $pond;
            } else {
                $noAltaPonds[] = $pond;
            }
        }

        if ($altaPonds === [] || $noAltaPonds === []) {
            return true;
        }

        $minAlta = min($altaPonds);
        $maxNoAlta = max($noAltaPonds);

        if ($minAlta <= $maxNoAlta) {
            $this->addConfigGeneralError(
                'Los indicadores de Prioridad Alta deben tener una ponderación estrictamente mayor que la de los demás indicadores en convenio'
                ." (mínima Alta: {$minAlta}%; máxima no-Alta: {$maxNoAlta}%)."
            );

            return false;
        }

        return true;
    }

    private function addConfigGeneralError(string $message): void
    {
        $type = IndicatorType::query()->find($this->indicatorTypeId);

        if ($type?->usesInternalUnitMeasurement() && $this->configMeasurementUnitId) {
            $codigo = MetMeasurementUnit::query()
                ->where('id', $this->configMeasurementUnitId)
                ->value('codigo');

            if (is_string($codigo) && $codigo !== '') {
                $message = "Unidad {$codigo}: {$message}";
            }
        }

        $this->addError('configGeneral', $message);
    }

    private function configColumnAllAgrupaSs(): bool
    {
        $aplicaConfigs = array_filter(
            $this->configs,
            fn (array $cfg): bool => (bool) ($cfg['aplica'] ?? false),
        );

        if ($aplicaConfigs === []) {
            return false;
        }

        foreach ($aplicaConfigs as $cfg) {
            if (! (bool) ($cfg['agrupa_ss'] ?? false)) {
                return false;
            }
        }

        return true;
    }

    private function configColumnAllConvenido(): bool
    {
        $aplicaConfigs = array_filter(
            $this->configs,
            fn (array $cfg): bool => (bool) ($cfg['aplica'] ?? false),
        );

        if ($aplicaConfigs === []) {
            return false;
        }

        foreach ($aplicaConfigs as $cfg) {
            if (! (bool) ($cfg['seleccionado'] ?? false)) {
                return false;
            }
        }

        return true;
    }
}
