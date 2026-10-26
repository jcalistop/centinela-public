<?php

namespace App\Livewire\MetasInstrument;

use App\Enums\IndicatorType\EtapaVigente;
use App\Jobs\MetCalculateResultsJob;
use App\Livewire\MetasInstrument\Concerns\ComputesMetStageCaptureMetrics;
use App\Livewire\MetasInstrument\Concerns\InteractsWithMetStageCapture;
use App\Livewire\MetasInstrument\Concerns\PreventsDuplicateJob;
use App\Livewire\MetasInstrument\Concerns\ResolvesInstrumentIndicatorTypeId;
use App\Models\IndicatorType;
use App\Models\MetFinalEvaluation;
use App\Models\MetIndicator;
use App\Models\MetIndicatorAppeal;
use App\Models\MetPreliminaryEvaluation;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class FinalEvaluationComponent extends Component
{
    use ComputesMetStageCaptureMetrics;
    use InteractsWithMetStageCapture;
    use PreventsDuplicateJob;
    use ResolvesInstrumentIndicatorTypeId;

    public int $indicatorTypeId;

    public int $centerId;

    private ?IndicatorType $indicatorTypeModel = null;

    /** @var array<int, array<string, mixed>> */
    public array $evaluations = [];

    public bool $showResolutionNotesModal = false;

    public ?int $resolutionNotesIndicatorId = null;

    public string $resolutionNotesDraft = '';

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

        $existing = MetFinalEvaluation::query()
            ->where('cen_center_id', $this->centerId)
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $depId)
            ->get()
            ->keyBy('met_indicator_id');

        $preliminaries = MetPreliminaryEvaluation::query()
            ->where('cen_center_id', $this->centerId)
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $depId)
            ->get()
            ->keyBy('met_indicator_id');

        $appeals = MetIndicatorAppeal::query()
            ->where('cen_center_id', $this->centerId)
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $depId)
            ->pluck('id', 'met_indicator_id');

        foreach ($this->convenidoConfigurationsForCenter($this->centerId) as $configuration) {
            $indicator = $configuration->metIndicator;
            if (! $indicator) {
                continue;
            }

            $row = $existing->get($indicator->id);
            $prelim = $preliminaries->get($indicator->id);
            $hasAppeal = $appeals->has($indicator->id);

            $prelimNumerador = $this->formatCaptureDecimal($prelim?->numerador);
            $prelimDenominador = $this->formatCaptureDecimal($prelim?->denominador);

            $numerador = $this->formatCaptureDecimal($row?->numerador);
            $denominador = $this->formatCaptureDecimal($row?->denominador);

            $apelacionAcogida = '';
            if ($row?->apelacion_acogida !== null) {
                $apelacionAcogida = $row->apelacion_acogida ? '1' : '0';
            }

            if ($hasAppeal && $apelacionAcogida !== '1') {
                $numerador = $prelimNumerador;
                $denominador = $prelimDenominador;
            }

            $this->evaluations[$indicator->id] = [
                'clave' => $indicator->clave,
                'nombre' => $indicator->nombre,
                'meta_efectiva' => $configuration->meta_efectiva !== null
                    ? (string) $configuration->meta_efectiva
                    : ($indicator->meta_valor !== null ? (string) $indicator->meta_valor : ''),
                'meta_operador' => $indicator->meta_operador?->value ?? '>=',
                'has_appeal' => $hasAppeal,
                'apelacion_acogida' => $apelacionAcogida,
                'resolucion_notas' => $row?->resolucion_notas ?? '',
                'prelim_numerador' => $prelimNumerador,
                'prelim_denominador' => $prelimDenominador,
                'numerador' => $numerador,
                'denominador' => $denominador,
                'ponderacion' => $configuration->ponderacion !== null ? (string) $configuration->ponderacion : '0',
                'decimales' => $indicator->decimales ?? 2,
                'unidad_medida' => $indicator->unidad_medida,
            ];
        }
    }

    public function fillFromPreliminary(): void
    {
        if (! $this->canWriteStage(EtapaVigente::Final)) {
            abort(403);
        }

        foreach ($this->evaluations as $indicatorId => $row) {
            $this->evaluations[$indicatorId]['numerador'] = $row['prelim_numerador'];
            $this->evaluations[$indicatorId]['denominador'] = $row['prelim_denominador'];
        }
    }

    public function clearAllData(): void
    {
        if (! $this->canWriteStage(EtapaVigente::Final)) {
            abort(403);
        }

        foreach ($this->evaluations as $indicatorId => $row) {
            $this->evaluations[$indicatorId]['numerador'] = '';
            $this->evaluations[$indicatorId]['denominador'] = '';
        }
    }

    public function openResolutionNotesModal(int $indicatorId): void
    {
        if (! isset($this->evaluations[$indicatorId])) {
            abort(404);
        }

        $this->resolutionNotesIndicatorId = $indicatorId;
        $this->resolutionNotesDraft = $this->evaluations[$indicatorId]['resolucion_notas'];
        $this->showResolutionNotesModal = true;
    }

    public function applyResolutionNotes(): void
    {
        if (! $this->canWriteStage(EtapaVigente::Final)) {
            abort(403);
        }

        if (
            $this->resolutionNotesIndicatorId === null
            || ! isset($this->evaluations[$this->resolutionNotesIndicatorId])
        ) {
            return;
        }

        $this->evaluations[$this->resolutionNotesIndicatorId]['resolucion_notas'] = trim(
            $this->resolutionNotesDraft,
        );

        $this->closeResolutionNotesModal();
    }

    public function closeResolutionNotesModal(): void
    {
        $this->showResolutionNotesModal = false;
        $this->resolutionNotesIndicatorId = null;
        $this->resolutionNotesDraft = '';
    }

    public function updated(string $property): void
    {
        if (! preg_match('/^evaluations\.(\d+)\.apelacion_acogida$/', $property, $matches)) {
            return;
        }

        $indicatorId = (int) $matches[1];

        if (! isset($this->evaluations[$indicatorId])) {
            return;
        }

        if ($this->evaluations[$indicatorId]['apelacion_acogida'] !== '1') {
            $this->evaluations[$indicatorId]['numerador'] = $this->evaluations[$indicatorId]['prelim_numerador'];
            $this->evaluations[$indicatorId]['denominador'] = $this->evaluations[$indicatorId]['prelim_denominador'];
        }
    }

    public function save(): void
    {
        if (! $this->canWriteStage(EtapaVigente::Final)) {
            abort(403);
        }

        $rules = [];
        $messages = [];

        foreach ($this->evaluations as $indicatorId => $row) {
            if ($row['has_appeal']) {
                $rules["evaluations.{$indicatorId}.apelacion_acogida"] = ['required', 'in:0,1'];
                $messages["evaluations.{$indicatorId}.apelacion_acogida.required"] =
                    'Debe indicar si la apelación fue acogida (Sí o No) antes de guardar.';
            }
        }

        if ($rules !== []) {
            $this->validate($rules, $messages);
        }

        $yearId = $this->currentIndicatorYear()->id;
        $depId = (int) $this->authUser()->cen_hie_dependency_id;
        $userId = $this->authUser()->id;

        foreach ($this->evaluations as $indicatorId => $row) {
            $canEditCapture = $row['has_appeal'] && $row['apelacion_acogida'] === '1';

            if ($canEditCapture) {
                $numerador = $row['numerador'] !== '' ? $row['numerador'] : null;
                $denominador = $row['denominador'] !== '' ? $row['denominador'] : null;
            } else {
                $numerador = $row['prelim_numerador'] !== '' ? $row['prelim_numerador'] : null;
                $denominador = $row['prelim_denominador'] !== '' ? $row['prelim_denominador'] : null;
            }

            $apelacionAcogida = null;
            if ($row['has_appeal']) {
                $apelacionAcogida = $row['apelacion_acogida'] === '1';
            }

            MetFinalEvaluation::query()->updateOrCreate(
                [
                    'met_indicator_id' => (int) $indicatorId,
                    'cen_center_id' => $this->centerId,
                    'year_id' => $yearId,
                ],
                [
                    'cen_hie_dependency_id' => $depId,
                    'numerador' => $numerador,
                    'denominador' => $denominador,
                    'apelacion_acogida' => $apelacionAcogida,
                    'resolucion_notas' => $row['resolucion_notas'] !== '' ? $row['resolucion_notas'] : null,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ],
            );
        }

        session()->flash('stage_message', 'Evaluación final guardada correctamente.');
    }

    public function recalculate(): void
    {
        if (! $this->canWriteStage(EtapaVigente::Final)) {
            abort(403);
        }

        $this->save();

        $yearId = $this->currentIndicatorYear()->id;
        $dependencyId = (int) $this->authUser()->cen_hie_dependency_id;
        $fingerprint = "calc:{$yearId}:{$dependencyId}:{$this->indicatorTypeId}:".EtapaVigente::Final->value;

        if (! $this->shouldDispatchJob($fingerprint)) {
            session()->flash('stage_message', 'Ya hay un cálculo pendiente. Espere unos segundos.');

            return;
        }

        MetCalculateResultsJob::dispatchStage(
            EtapaVigente::Final->value,
            $yearId,
            $dependencyId,
            $this->indicatorTypeId,
        );

        session()->flash('stage_message', 'Evaluación final guardada. Recálculo enviado a cola.');
    }

    protected function canEditCaptureValues(array $row): bool
    {
        return $row['has_appeal'] && ($row['apelacion_acogida'] ?? '') === '1';
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
            $displayRows[$indicatorId] = array_merge($row, $metrics, [
                'can_edit_capture' => $this->canEditCaptureValues($row),
            ]);

            if ($metrics['cumpl_ponderado'] !== null) {
                $cumplimientoGlobal += $metrics['cumpl_ponderado'];
                $hasCumplimientoGlobal = true;
            }
        }

        return view('livewire.metas-instrument.final-evaluation', [
            'canWrite' => $this->canWriteStage(EtapaVigente::Final),
            'canWriteRole' => $this->roleCanWriteStage(EtapaVigente::Final),
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
