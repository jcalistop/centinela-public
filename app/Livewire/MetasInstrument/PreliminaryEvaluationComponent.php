<?php

namespace App\Livewire\MetasInstrument;

use App\Enums\IndicatorType\EtapaVigente;
use App\Jobs\MetCalculateResultsJob;
use App\Livewire\MetasInstrument\Concerns\ComputesMetStageCaptureMetrics;
use App\Livewire\MetasInstrument\Concerns\InteractsWithMetStageCapture;
use App\Livewire\MetasInstrument\Concerns\PreventsDuplicateJob;
use App\Livewire\MetasInstrument\Concerns\ResolvesInstrumentIndicatorTypeId;
use App\Models\IndicatorType;
use App\Models\MetIndicator;
use App\Models\MetPreliminaryEvaluation;
use App\Services\Metas\MetAutoevaluationCaptureService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class PreliminaryEvaluationComponent extends Component
{
    use ComputesMetStageCaptureMetrics;
    use InteractsWithMetStageCapture;
    use PreventsDuplicateJob;
    use ResolvesInstrumentIndicatorTypeId;

    public int $indicatorTypeId;

    public int $centerId;

    private ?IndicatorType $indicatorTypeModel = null;

    /** @var array<int, array{clave: string, nombre: string, meta_efectiva: string, numerador: string, denominador: string, ponderacion: string, decimales: int, unidad_medida: ?string}> */
    public array $evaluations = [];

    public function mount(?int $indicatorTypeId = null, ?int $centerId = null): void
    {
        $this->indicatorTypeId = $this->resolveInstrumentIndicatorTypeId($indicatorTypeId);
        $this->centerId = (int) $centerId;

        $this->loadEvaluations();
    }

    public function loadEvaluations(): void
    {
        $this->evaluations = [];
        $yearId = $this->currentIndicatorYear()->id;
        $depId = (int) $this->authUser()->cen_hie_dependency_id;

        $existing = MetPreliminaryEvaluation::query()
            ->where('cen_center_id', $this->centerId)
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $depId)
            ->get()
            ->keyBy('met_indicator_id');

        foreach ($this->convenidoConfigurationsForCenter($this->centerId) as $configuration) {
            $indicator = $configuration->metIndicator;
            if (! $indicator) {
                continue;
            }

            $row = $existing->get($indicator->id);

            $this->evaluations[$indicator->id] = [
                'clave' => $indicator->clave,
                'nombre' => $indicator->nombre,
                'meta_efectiva' => $configuration->meta_efectiva !== null
                    ? (string) $configuration->meta_efectiva
                    : ($indicator->meta_valor !== null ? (string) $indicator->meta_valor : ''),
                'meta_operador' => $indicator->meta_operador?->value ?? '>=',
                'numerador' => $this->formatCaptureDecimal($row?->numerador),
                'denominador' => $this->formatCaptureDecimal($row?->denominador),
                'ponderacion' => $configuration->ponderacion !== null ? (string) $configuration->ponderacion : '0',
                'decimales' => $indicator->decimales ?? 2,
                'unidad_medida' => $indicator->unidad_medida,
            ];
        }
    }

    public function fillFromAutoevaluacion(): void
    {
        if (! $this->canWriteStage(EtapaVigente::Preliminar)) {
            abort(403);
        }

        $yearId = $this->currentIndicatorYear()->id;
        $dependencyId = (int) $this->authUser()->cen_hie_dependency_id;
        $captures = app(MetAutoevaluationCaptureService::class)->capturesForCenter(
            $this->indicatorTypeId,
            $yearId,
            $dependencyId,
            $this->centerId,
        );

        foreach ($this->evaluations as $indicatorId => $row) {
            $capture = $captures[(int) $indicatorId] ?? ['numerador' => null, 'denominador' => null];
            $this->evaluations[$indicatorId]['numerador'] = $this->formatCaptureDecimal($capture['numerador']);
            $this->evaluations[$indicatorId]['denominador'] = $this->formatCaptureDecimal($capture['denominador']);
        }
    }

    public function clearAllData(): void
    {
        if (! $this->canWriteStage(EtapaVigente::Preliminar)) {
            abort(403);
        }

        foreach ($this->evaluations as $indicatorId => $row) {
            $this->evaluations[$indicatorId]['numerador'] = '';
            $this->evaluations[$indicatorId]['denominador'] = '';
        }
    }

    public function save(): void
    {
        if (! $this->canWriteStage(EtapaVigente::Preliminar)) {
            abort(403);
        }

        $yearId = $this->currentIndicatorYear()->id;
        $depId = (int) $this->authUser()->cen_hie_dependency_id;
        $userId = $this->authUser()->id;

        foreach ($this->evaluations as $indicatorId => $row) {
            MetPreliminaryEvaluation::query()->updateOrCreate(
                [
                    'met_indicator_id' => (int) $indicatorId,
                    'cen_center_id' => $this->centerId,
                    'year_id' => $yearId,
                ],
                [
                    'cen_hie_dependency_id' => $depId,
                    'numerador' => $row['numerador'] !== '' ? $row['numerador'] : null,
                    'denominador' => $row['denominador'] !== '' ? $row['denominador'] : null,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ],
            );
        }

        session()->flash('stage_message', 'Evaluación preliminar guardada correctamente.');
    }

    public function recalculate(): void
    {
        if (! $this->canWriteStage(EtapaVigente::Preliminar)) {
            abort(403);
        }

        $this->save();

        $yearId = $this->currentIndicatorYear()->id;
        $dependencyId = (int) $this->authUser()->cen_hie_dependency_id;
        $fingerprint = "calc:{$yearId}:{$dependencyId}:{$this->indicatorTypeId}:".EtapaVigente::Preliminar->value;

        if (! $this->shouldDispatchJob($fingerprint)) {
            session()->flash('stage_message', 'Ya hay un cálculo pendiente. Espere unos segundos.');

            return;
        }

        MetCalculateResultsJob::dispatchStage(
            EtapaVigente::Preliminar->value,
            $yearId,
            $dependencyId,
            $this->indicatorTypeId,
        );

        session()->flash('stage_message', 'Evaluación preliminar guardada. Recálculo enviado a cola.');
    }

    public function render(): View
    {
        $indicators = MetIndicator::query()
            ->with(['formula', 'dataFields'])
            ->whereIn('id', array_keys($this->evaluations))
            ->get()
            ->keyBy('id');

        /** @var array<int, array<string, mixed>> $displayRows */
        $displayRows = [];
        $cumplimientoGlobal = 0.0;
        $hasCumplimientoGlobal = false;

        foreach ($this->evaluations as $indicatorId => $row) {
            $metrics = $this->metricsForEvaluationRow($indicators->get((int) $indicatorId), $row);
            $displayRows[$indicatorId] = array_merge($row, $metrics);

            if ($metrics['cumpl_ponderado'] !== null) {
                $cumplimientoGlobal += $metrics['cumpl_ponderado'];
                $hasCumplimientoGlobal = true;
            }
        }

        return view('livewire.metas-instrument.preliminary-evaluation', [
            'canWrite' => $this->canWriteStage(EtapaVigente::Preliminar),
            'canWriteRole' => $this->roleCanWriteStage(EtapaVigente::Preliminar),
            'etapaVigente' => $this->currentEtapaVigente(),
            'displayRows' => $displayRows,
            'cumplimientoGlobal' => $cumplimientoGlobal,
            'hasCumplimientoGlobal' => $hasCumplimientoGlobal,
            'indicatorTypeRoute' => $this->getIndicatorTypeRoute(),
        ]);
    }

    private function getIndicatorTypeRoute(): string
    {
        $this->indicatorTypeModel ??= IndicatorType::query()->findOrFail($this->indicatorTypeId);

        return $this->indicatorTypeModel->route;
    }
}
