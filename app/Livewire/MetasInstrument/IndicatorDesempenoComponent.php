<?php

namespace App\Livewire\MetasInstrument;

use App\Livewire\MetasInstrument\Concerns\AssertsMetIndicatorCatalogScope;
use App\Livewire\MetasInstrument\Concerns\InteractsWithComplexFormulaVariablesModal;
use App\Livewire\MetasInstrument\Concerns\ResolvesInstrumentIndicatorTypeId;
use App\Models\IndicatorType;
use App\Models\MetIndicator;
use App\Services\Metas\MetIndicatorDataModalService;
use App\Services\Metas\MetIndicatorDesempenoByEstablishmentService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class IndicatorDesempenoComponent extends Component
{
    use AssertsMetIndicatorCatalogScope;
    use InteractsWithComplexFormulaVariablesModal;
    use ResolvesInstrumentIndicatorTypeId;

    public int $indicatorTypeId = 0;

    public int $indicatorId = 0;

    public string $indicatorTypeRoute = '';

    public bool $canShowCargaButton = false;

    public string $indicadorNombre = '';

    /** @var array<int, array<string, mixed>> */
    public array $desempenoData = [];

    public bool $usesComplexPrimaryComponents = false;

    public ?float $umbralSuperior = null;

    public ?float $umbralInferior = null;

    public ?float $promedioCumplimiento = null;

    public function mount(
        ?int $indicatorTypeId = null,
        ?int $indicatorId = null,
        ?IndicatorType $indicatorType = null,
        ?MetIndicator $indicator = null,
    ): void {
        $this->authorizeDesempenoView();

        $resolvedTypeId = $indicatorType?->id ?? $indicatorTypeId ?? ($this->indicatorTypeId > 0 ? $this->indicatorTypeId : null);
        $this->indicatorTypeId = $this->resolveInstrumentIndicatorTypeId(
            $resolvedTypeId !== null ? (int) $resolvedTypeId : null
        );

        $indicatorTypeModel = IndicatorType::query()->findOrFail($this->indicatorTypeId);
        $this->assertMetasInstrumentIndicatorType($indicatorTypeModel);
        $this->indicatorTypeRoute = (string) $indicatorTypeModel->route;
        $this->canShowCargaButton = $this->authCatalogUser()->hasRole(['control-gestion', 'admin', 'referente']);

        $resolvedIndicatorId = $indicator?->id ?? $indicatorId ?? ($this->indicatorId > 0 ? $this->indicatorId : null);
        abort_unless($resolvedIndicatorId !== null, 404);

        $indicatorModel = $indicator ?? MetIndicator::query()->findOrFail($resolvedIndicatorId);
        $this->assertIndicatorInScope($indicatorModel, $indicatorTypeModel);

        $this->indicatorId = (int) $indicatorModel->id;

        $payload = app(MetIndicatorDesempenoByEstablishmentService::class)->buildForIndicator(
            $indicatorModel,
            $this->authCatalogUser()->cen_hie_dependency_id,
            $this->currentIndicatorYear()->id,
        );

        $this->indicadorNombre = $payload['indicador_nombre'];
        $this->umbralSuperior = $payload['umbral_superior'];
        $this->umbralInferior = $payload['umbral_inferior'];
        $this->usesComplexPrimaryComponents = $payload['uses_complex_primary_components'];
        $this->desempenoData = $payload['rows'];
        $this->promedioCumplimiento = $payload['promedio_cumplimiento'];
    }

    public function openDataModal(int $centerId): void
    {
        $payload = app(MetIndicatorDataModalService::class)->payload(
            $this->indicatorTypeId,
            $this->indicatorId,
            $centerId,
            $this->authCatalogUser(),
            $this->currentIndicatorYear()->id,
            restrictToAutoevaluacionEtapa: false,
        );

        $this->dispatch('open-data-modal', ...$payload);
    }

    public function render(): View
    {
        return view('livewire.metas-instrument.indicator-desempeno-component', [
            'indicatorYearAno' => $this->currentIndicatorYear()->ano,
        ]);
    }

    protected function complexFormulaVariablesModalIndicatorTypeId(): int
    {
        return $this->indicatorTypeId;
    }
}
