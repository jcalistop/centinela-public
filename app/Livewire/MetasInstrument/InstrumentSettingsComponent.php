<?php

namespace App\Livewire\MetasInstrument;

use App\Enums\IndicatorType\EtapaVigente;
use App\Jobs\MetCalculateResultsJob;
use App\Livewire\MetasInstrument\Concerns\PreventsDuplicateJob;
use App\Livewire\MetasInstrument\Concerns\ResolvesInstrumentIndicatorTypeId;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeGlobalSetting;
use App\Models\IndicatorTypeSetting;
use App\Models\MetStageTransition;
use App\Models\User;
use App\Models\Year;
use App\Services\Metas\MetCalculationQueueStatusService;
use App\Services\Metas\MetRecalculationDispatcher;
use App\Support\Metas\MetSemaforoThresholds;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;

class InstrumentSettingsComponent extends Component
{
    use PreventsDuplicateJob;
    use ResolvesInstrumentIndicatorTypeId;
    use WithoutUrlPagination;
    use WithPagination;

    private const STAGE_HISTORY_PER_PAGE = 10;

    public int $indicatorTypeId;

    public string $etapaVigente = 'NO_INICIADO';

    public bool $habilitado = false;

    public string $motivoRetroceso = '';

    public ?int $settingId = null;

    public int $minIndicadores = 4;

    public string $minPonderacion = '0.00';

    public string $maxPonderacion = '100.00';

    public ?int $globalSettingId = null;

    public string $semaforoVerdeMin = '100.00';

    public string $semaforoAmarilloMin = '75.00';

    public bool $semaforoVerdeEsHeredado = true;

    public bool $semaforoAmarilloEsHeredado = true;

    public bool $canEditGlobalParams = false;

    public bool $showStageHistoryModal = false;

    public function mount(?int $indicatorTypeId = null): void
    {
        if (! $this->authenticatedUser()->hasRole(['parametrizador', 'admin', 'control-gestion'])) {
            abort(403);
        }

        $this->indicatorTypeId = $this->resolveInstrumentIndicatorTypeId($indicatorTypeId);
        $this->canEditGlobalParams = $this->authenticatedUser()->hasRole(['parametrizador', 'admin']);

        $setting = IndicatorTypeSetting::getData($this->indicatorTypeId);
        $this->settingId = $setting->id;
        $this->etapaVigente = $setting->etapa_vigente->value;
        $this->habilitado = (bool) $setting->habilitado;

        $globalSetting = IndicatorTypeGlobalSetting::getData($this->indicatorTypeId);
        $this->globalSettingId = $globalSetting->id;
        $this->minIndicadores = $globalSetting->min_indicadores;
        $this->minPonderacion = (string) $globalSetting->min_ponderacion;
        $this->maxPonderacion = (string) $globalSetting->max_ponderacion;
        $this->hydrateSemaforoFromSetting($globalSetting);
    }

    public function render(): View
    {
        $calculationQueue = app(MetCalculationQueueStatusService::class)->statusForScope(
            (int) $this->authenticatedUser()->cen_hie_dependency_id,
            $this->indicatorTypeId,
        );

        $stageTransitionsQuery = MetStageTransition::query()
            ->where('indicator_type_id', $this->indicatorTypeId)
            ->where('cen_hie_dependency_id', $this->authenticatedUser()->cen_hie_dependency_id)
            ->where('year_id', $this->indicatorYear()->id);

        $stageTransitionsCount = (clone $stageTransitionsQuery)->count();

        $stageTransitions = $this->resolveStageHistoryPaginator($stageTransitionsQuery);

        return view('livewire.metas-instrument.instrument-settings', compact(
            'calculationQueue',
            'stageTransitions',
            'stageTransitionsCount',
        ));
    }

    public function openStageHistoryModal(): void
    {
        if (! $this->authenticatedUser()->hasRole(['admin', 'control-gestion'])) {
            abort(403);
        }

        $this->resetPage('stageHistoryPage');
        $this->showStageHistoryModal = true;
    }

    public function closeStageHistoryModal(): void
    {
        $this->showStageHistoryModal = false;
        $this->resetPage('stageHistoryPage');
    }

    /**
     * @param  Builder<MetStageTransition>  $query
     */
    private function resolveStageHistoryPaginator($query): LengthAwarePaginator
    {
        if (! $this->showStageHistoryModal) {
            return new Paginator([], 0, self::STAGE_HISTORY_PER_PAGE);
        }

        return (clone $query)
            ->with('user')
            ->orderByDesc('created_at')
            ->paginate(
                perPage: self::STAGE_HISTORY_PER_PAGE,
                pageName: 'stageHistoryPage',
            );
    }

    public function saveEtapa(): void
    {
        if (! $this->authenticatedUser()->hasRole(['admin', 'control-gestion'])) {
            abort(403);
        }

        $allowedEtapaValues = array_map(
            fn (EtapaVigente $case) => $case->value,
            EtapaVigente::cases(),
        );

        $this->validate([
            'etapaVigente' => ['required', 'in:'.implode(',', $allowedEtapaValues)],
        ]);

        $setting = IndicatorTypeSetting::findOrFail($this->settingId);
        $etapaAnterior = $setting->etapa_vigente;
        $nuevaEtapa = EtapaVigente::from($this->etapaVigente);

        if ($etapaAnterior->esRetrocesoHacia($nuevaEtapa)) {
            $this->validate([
                'motivoRetroceso' => ['required', 'string', 'min:3'],
            ]);
        }

        $setting->update([
            'etapa_vigente' => $nuevaEtapa,
            'habilitado' => $nuevaEtapa->habilitaAccesoReferente(),
        ]);

        MetStageTransition::query()->create([
            'indicator_type_id' => $this->indicatorTypeId,
            'cen_hie_dependency_id' => (int) $this->authenticatedUser()->cen_hie_dependency_id,
            'year_id' => $this->indicatorYear()->id,
            'etapa_desde' => $etapaAnterior,
            'etapa_hasta' => $nuevaEtapa,
            'motivo' => $etapaAnterior->esRetrocesoHacia($nuevaEtapa) ? $this->motivoRetroceso : null,
            'user_id' => $this->authenticatedUser()->id,
            'created_at' => now(),
        ]);

        $this->habilitado = $nuevaEtapa->habilitaAccesoReferente();
        $this->motivoRetroceso = '';

        session()->flash('settings_message', 'Etapa vigente actualizada a: '.$nuevaEtapa->label());
    }

    public function recalculateNow(): void
    {
        if (! $this->authenticatedUser()->hasRole(['admin', 'control-gestion'])) {
            abort(403);
        }

        $setting = IndicatorTypeSetting::findOrFail($this->settingId);
        $etapa = $setting->etapa_vigente;

        if (! in_array($etapa, [
            EtapaVigente::Formulacion,
            EtapaVigente::Autoevaluacion,
            EtapaVigente::Preliminar,
            EtapaVigente::Final,
        ], true)) {
            session()->flash(
                'settings_message',
                'El recálculo manual no está disponible en la etapa vigente: '.$etapa->label().'.',
            );

            return;
        }

        $yearId = $this->indicatorYear()->id;
        $dependencyId = (int) $this->authenticatedUser()->cen_hie_dependency_id;
        $fingerprint = "calc:{$yearId}:{$dependencyId}:{$this->indicatorTypeId}:".$etapa->value;

        if (! $this->shouldDispatchJob($fingerprint)) {
            session()->flash('settings_message', 'Ya hay un cálculo pendiente. Espere unos segundos.');

            return;
        }

        if ($etapa === EtapaVigente::Autoevaluacion || $etapa === EtapaVigente::Formulacion) {
            app(MetRecalculationDispatcher::class)->scheduleFullAutoevaluacion($yearId, $dependencyId, $this->indicatorTypeId);
        } else {
            MetCalculateResultsJob::dispatchStage($etapa->value, $yearId, $dependencyId, $this->indicatorTypeId);
        }

        session()->flash('settings_message', 'Recálculo de resultados enviado a cola ('.$etapa->label().').');
    }

    public function saveParams(): void
    {
        if (! $this->authenticatedUser()->hasRole(['parametrizador', 'admin'])) {
            abort(403);
        }

        $this->validate([
            'minIndicadores' => ['required', 'integer', 'min:1'],
            'minPonderacion' => ['required', 'numeric', 'min:0', 'max:99.99', 'lt:maxPonderacion'],
            'maxPonderacion' => ['required', 'numeric', 'max:100', 'gt:minPonderacion'],
        ]);

        if ($this->minIndicadores * (float) $this->maxPonderacion < 100) {
            $this->addError('minIndicadores', 'Con estos parámetros es imposible alcanzar la suma de 100.');

            return;
        }

        $globalSetting = IndicatorTypeGlobalSetting::findOrFail($this->globalSettingId);
        $globalSetting->update([
            'min_indicadores' => $this->minIndicadores,
            'min_ponderacion' => $this->minPonderacion,
            'max_ponderacion' => $this->maxPonderacion,
        ]);

        session()->flash('settings_message', 'Parámetros de convenio actualizados correctamente.');
    }

    public function saveSemaforo(): void
    {
        if (! $this->authenticatedUser()->hasRole(['parametrizador', 'admin'])) {
            abort(403);
        }

        $this->validate([
            'semaforoAmarilloMin' => ['required', 'numeric', 'min:0', 'max:99.99', 'lt:semaforoVerdeMin'],
            'semaforoVerdeMin' => ['required', 'numeric', 'min:0.01', 'max:100', 'gt:semaforoAmarilloMin'],
        ]);

        $globalSetting = IndicatorTypeGlobalSetting::findOrFail($this->globalSettingId);
        $globalSetting->update([
            'semaforo_verde_min' => $this->semaforoVerdeMin,
            'semaforo_amarillo_min' => $this->semaforoAmarilloMin,
        ]);

        MetSemaforoThresholds::forgetResolved($this->indicatorTypeId, $this->indicatorYear()->id);
        $this->hydrateSemaforoFromSetting($globalSetting->fresh());

        session()->flash('settings_message', 'Umbrales de semáforo global actualizados correctamente.');
    }

    private function hydrateSemaforoFromSetting(IndicatorTypeGlobalSetting $globalSetting): void
    {
        $route = (string) (IndicatorType::query()->whereKey($this->indicatorTypeId)->value('route') ?? '');
        $resolved = MetSemaforoThresholds::forIndicatorType(
            $this->indicatorTypeId,
            $route !== '' ? $route : null,
            $this->indicatorYear()->id,
        );

        $this->semaforoVerdeEsHeredado = $globalSetting->semaforo_verde_min === null;
        $this->semaforoAmarilloEsHeredado = $globalSetting->semaforo_amarillo_min === null;
        $this->semaforoVerdeMin = number_format($resolved->greenMinPercent, 2, '.', '');
        $this->semaforoAmarilloMin = number_format($resolved->yellowMinPercent, 2, '.', '');
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
}
