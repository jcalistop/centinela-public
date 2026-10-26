<?php

namespace App\Livewire\MetasInstrument;

use App\Livewire\MetasInstrument\Concerns\ResolvesInstrumentIndicatorTypeId;
use App\Models\CenCenter;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeGlobalSetting;
use App\Models\IndicatorTypeSetting;
use App\Models\MetCenterInclusion;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetIndicatorReferente;
use App\Models\MetMeasurementUnit;
use App\Models\User;
use App\Models\Year;
use App\Services\Metas\MetIndicatorPeriodicityResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class IndicatorConfigurationComponent extends Component
{
    use ResolvesInstrumentIndicatorTypeId;

    /** @var array<int, array{seleccionado: bool, ponderacion: string}> */
    public array $configs = [];

    /** @var array<int, int[]> */
    public array $referentesPorIndicador = [];

    /** @var array<int, string> */
    public array $nuevoReferentePorIndicador = [];

    public int $indicatorTypeId;

    public ?int $selectedCenterId = null;

    public ?int $selectedMeasurementUnitId = null;

    public function mount(?int $indicatorTypeId = null): void
    {
        if (! $this->authenticatedUser()->hasRole(['control-gestion', 'admin'])) {
            abort(403);
        }

        $this->indicatorTypeId = $this->resolveInstrumentIndicatorTypeId($indicatorTypeId);

        $this->loadConfigs();
    }

    public function render(): View
    {
        $yearId = $this->indicatorYear()->id;
        $depId = $this->authenticatedUser()->cen_hie_dependency_id;

        $indicators = MetIndicator::query()
            ->where('indicator_type_id', $this->indicatorTypeId)
            ->where('year_id', $yearId)
            ->where('estado', true)
            ->with('dataFields')
            ->orderBy('orden')
            ->orderBy('clave')
            ->get();

        $periodicidadPorIndicadorId = [];
        $periodicityResolver = app(MetIndicatorPeriodicityResolver::class);

        foreach ($indicators as $indicator) {
            $periodicidadPorIndicadorId[$indicator->id] = $periodicityResolver->resolveForCarga(
                $indicator,
                $depId,
                $yearId,
            )->value;
        }

        $includedCenters = CenCenter::query()
            ->whereIn('id', MetCenterInclusion::query()
                ->where('year_id', $yearId)
                ->where('cen_hie_dependency_id', $depId)
                ->where('indicator_type_id', $this->indicatorTypeId)
                ->pluck('cen_center_id'))
            ->orderBy('nombre_corto')
            ->get();

        $setting = IndicatorTypeSetting::getData($this->indicatorTypeId);
        $isCerrado = $setting && ! $setting->etapa_vigente->permiteConfiguracionEstructural();

        $globalSetting = IndicatorTypeGlobalSetting::getData($this->indicatorTypeId);

        $instrumentType = IndicatorType::query()->find($this->indicatorTypeId);
        $usesInternalUnitMeasurement = $instrumentType?->usesInternalUnitMeasurement() ?? false;

        $measurementUnits = collect();

        if ($usesInternalUnitMeasurement && $this->selectedCenterId) {
            $measurementUnits = MetMeasurementUnit::query()
                ->where('cen_center_id', $this->selectedCenterId)
                ->where('year_id', $yearId)
                ->where('cen_hie_dependency_id', $depId)
                ->with('unitType')
                ->orderBy('codigo')
                ->get();
        }

        return view('livewire.metas-instrument.indicator-configuration', compact(
            'indicators',
            'includedCenters',
            'isCerrado',
            'globalSetting',
            'usesInternalUnitMeasurement',
            'measurementUnits',
            'periodicidadPorIndicadorId',
        ));
    }

    public function save(): void
    {
        $yearId = $this->indicatorYear()->id;
        $depId = $this->authenticatedUser()->cen_hie_dependency_id;

        $setting = IndicatorTypeSetting::getData($this->indicatorTypeId);

        if (! $setting->etapa_vigente->permiteConfiguracionEstructural()) {
            $this->addError('general', 'No se puede modificar la configuración en la etapa vigente: '.$setting->etapa_vigente->label().'.');

            return;
        }

        if (! $setting->etapa_vigente->permiteFormulacionConvenio()) {
            $this->addError('general', 'No se puede modificar el convenio fuera de la etapa de Formulación.');

            return;
        }

        if (! $this->selectedCenterId) {
            $this->addError('general', 'Debe seleccionar un establecimiento para guardar la configuración.');

            return;
        }

        if ($this->usesInternalInstrument() && ! $this->selectedMeasurementUnitId) {
            $this->addError('general', 'Debe seleccionar una unidad de medición para guardar el convenio (Ley 20.707).');

            return;
        }

        $globalSetting = IndicatorTypeGlobalSetting::getData($this->indicatorTypeId);

        $selectedConfigs = array_filter($this->configs, fn ($c) => (bool) ($c['seleccionado'] ?? false));
        $selectedCount = count($selectedConfigs);
        $sumPonderacion = array_sum(array_map('floatval', array_column($selectedConfigs, 'ponderacion')));

        if ($selectedCount < $globalSetting->min_indicadores) {
            $this->addError('general', "Debe seleccionar al menos {$globalSetting->min_indicadores} indicadores.");

            return;
        }

        foreach ($selectedConfigs as $config) {
            $pond = (float) ($config['ponderacion'] ?? 0);

            if ($pond < (float) $globalSetting->min_ponderacion) {
                $this->addError('general', "La ponderación de cada indicador seleccionado debe ser al menos {$globalSetting->min_ponderacion}.");

                return;
            }

            if ($pond > (float) $globalSetting->max_ponderacion) {
                $this->addError('general', "La ponderación de cada indicador seleccionado no puede superar {$globalSetting->max_ponderacion}.");

                return;
            }
        }

        if (abs($sumPonderacion - 100) > 0.001) {
            $this->addError('general', "La suma de ponderaciones de los indicadores seleccionados debe ser exactamente 100 (actual: {$sumPonderacion}).");

            return;
        }

        foreach ($this->configs as $indicatorId => $config) {
            $seleccionado = (bool) ($config['seleccionado'] ?? false);
            $ponderacion = $seleccionado && $config['ponderacion'] !== '' ? $config['ponderacion'] : null;

            $keys = [
                'met_indicator_id' => $indicatorId,
                'year_id' => $yearId,
                'cen_center_id' => $this->selectedCenterId,
            ];

            if ($this->usesInternalInstrument()) {
                $keys['met_measurement_unit_id'] = $this->selectedMeasurementUnitId;
            } else {
                $keys['met_measurement_unit_id'] = null;
            }

            MetIndicatorConfiguration::updateOrCreate(
                $keys,
                [
                    'seleccionado' => $seleccionado,
                    'ponderacion' => $ponderacion,
                ]
            );
        }

        foreach ($this->referentesPorIndicador as $indicatorId => $userIds) {
            MetIndicatorReferente::where('met_indicator_id', $indicatorId)
                ->where('year_id', $yearId)
                ->where('cen_hie_dependency_id', $depId)
                ->delete();

            foreach ($userIds as $userId) {
                MetIndicatorReferente::create([
                    'met_indicator_id' => $indicatorId,
                    'year_id' => $yearId,
                    'cen_hie_dependency_id' => $depId,
                    'user_id' => $userId,
                ]);
            }
        }

        session()->flash('config_message', 'Configuración guardada correctamente.');
    }

    public function updatedSelectedCenterId(): void
    {
        $this->configs = [];
        $this->selectedMeasurementUnitId = null;

        if ($this->usesInternalInstrument() && $this->selectedCenterId) {
            $this->selectedMeasurementUnitId = MetMeasurementUnit::query()
                ->where('cen_center_id', $this->selectedCenterId)
                ->where('year_id', $this->indicatorYear()->id)
                ->where('cen_hie_dependency_id', $this->authenticatedUser()->cen_hie_dependency_id)
                ->orderBy('codigo')
                ->value('id');
            $this->selectedMeasurementUnitId = $this->selectedMeasurementUnitId ? (int) $this->selectedMeasurementUnitId : null;
        }

        $this->loadConfigs();
    }

    public function updatedSelectedMeasurementUnitId(): void
    {
        $this->configs = [];
        $this->loadConfigs();
    }

    public function addReferente(int $indicatorId): void
    {
        $userId = (int) ($this->nuevoReferentePorIndicador[$indicatorId] ?? 0);

        if (! $userId) {
            return;
        }

        if (! isset($this->referentesPorIndicador[$indicatorId])) {
            $this->referentesPorIndicador[$indicatorId] = [];
        }

        if (! in_array($userId, $this->referentesPorIndicador[$indicatorId], true)) {
            $this->referentesPorIndicador[$indicatorId][] = $userId;
        }

        $this->nuevoReferentePorIndicador[$indicatorId] = '';
    }

    public function removeReferente(int $indicatorId, int $userId): void
    {
        $this->referentesPorIndicador[$indicatorId] = array_values(
            array_filter(
                $this->referentesPorIndicador[$indicatorId] ?? [],
                fn ($id) => $id !== $userId
            )
        );
    }

    #[On('indicator-catalog-updated')]
    public function refreshFromCatalog(): void
    {
        $this->configs = [];
        $this->loadConfigs();
    }

    private function authenticatedUser(): User
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }

    private function indicatorYear(): Year
    {
        $year = session('indicator_year');
        if (! $year instanceof Year) {
            abort(500, 'Año indicador no configurado en sesión.');
        }

        return $year;
    }

    private function loadConfigs(): void
    {
        $yearId = $this->indicatorYear()->id;
        $depId = $this->authenticatedUser()->cen_hie_dependency_id;

        $indicators = MetIndicator::query()
            ->where('indicator_type_id', $this->indicatorTypeId)
            ->where('year_id', $yearId)
            ->where('estado', true)
            ->pluck('id');

        foreach ($indicators as $id) {
            $this->configs[$id] = ['seleccionado' => false, 'ponderacion' => ''];
        }

        if ($this->selectedCenterId) {
            $existingQuery = MetIndicatorConfiguration::query()
                ->whereIn('met_indicator_id', $indicators)
                ->where('year_id', $yearId)
                ->where('cen_center_id', $this->selectedCenterId);

            if ($this->usesInternalInstrument()) {
                if ($this->selectedMeasurementUnitId) {
                    $existingQuery->where('met_measurement_unit_id', $this->selectedMeasurementUnitId);
                } else {
                    $existingQuery->whereRaw('1 = 0');
                }
            } else {
                $existingQuery->whereNull('met_measurement_unit_id');
            }

            $existing = $existingQuery->get();

            foreach ($existing as $config) {
                $this->configs[$config->met_indicator_id] = [
                    'seleccionado' => (bool) $config->seleccionado,
                    'ponderacion' => $config->ponderacion !== null ? (string) $config->ponderacion : '',
                ];
            }
        }

        $existingReferentes = MetIndicatorReferente::query()
            ->whereIn('met_indicator_id', $indicators)
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $depId)
            ->get(['met_indicator_id', 'user_id']);

        $this->referentesPorIndicador = [];
        foreach ($indicators as $id) {
            $this->referentesPorIndicador[$id] = [];
        }

        foreach ($existingReferentes as $referente) {
            $this->referentesPorIndicador[$referente->met_indicator_id][] = $referente->user_id;
        }
    }

    private function usesInternalInstrument(): bool
    {
        $type = IndicatorType::query()->find($this->indicatorTypeId);

        return $type?->usesInternalUnitMeasurement() ?? false;
    }
}
