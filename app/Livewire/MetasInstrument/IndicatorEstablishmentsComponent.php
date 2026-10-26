<?php

namespace App\Livewire\MetasInstrument;

use App\Livewire\MetasInstrument\Concerns\AssertsMetIndicatorCatalogScope;
use App\Livewire\MetasInstrument\Concerns\ResolvesInstrumentIndicatorTypeId;
use App\Models\IndicatorType;
use App\Models\MetIndicator;
use App\Services\Metas\MetIndicatorEstablishmentsOverviewService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class IndicatorEstablishmentsComponent extends Component
{
    use AssertsMetIndicatorCatalogScope;
    use ResolvesInstrumentIndicatorTypeId;

    public int $indicatorTypeId = 0;

    public int $indicatorId = 0;

    public bool $usesInternalUnitMeasurement = false;

    /** @var list<array<string, mixed>> */
    public array $establishmentsData = [];

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

        $resolvedIndicatorId = $indicator?->id ?? $indicatorId ?? ($this->indicatorId > 0 ? $this->indicatorId : null);
        abort_unless($resolvedIndicatorId !== null, 404);

        $indicatorModel = $indicator ?? MetIndicator::query()->findOrFail($resolvedIndicatorId);
        $this->assertIndicatorInScope($indicatorModel, $indicatorTypeModel);

        $this->indicatorId = (int) $indicatorModel->id;

        $payload = app(MetIndicatorEstablishmentsOverviewService::class)->buildForIndicator(
            $indicatorModel,
            $this->authCatalogUser()->cen_hie_dependency_id,
            $this->currentIndicatorYear()->id,
        );

        $this->usesInternalUnitMeasurement = $payload['uses_internal_unit_measurement'];
        $this->establishmentsData = $payload['rows'];
    }

    public function render(): View
    {
        return view('livewire.metas-instrument.indicator-establishments-component');
    }
}
