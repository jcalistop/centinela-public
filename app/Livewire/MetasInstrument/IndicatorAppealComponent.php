<?php

namespace App\Livewire\MetasInstrument;

use App\Enums\IndicatorType\EtapaVigente;
use App\Enums\Metas\AppealType;
use App\Livewire\MetasInstrument\Concerns\ComputesMetStageCaptureMetrics;
use App\Livewire\MetasInstrument\Concerns\InteractsWithMetStageCapture;
use App\Livewire\MetasInstrument\Concerns\ResolvesInstrumentIndicatorTypeId;
use App\Models\IndicatorType;
use App\Models\MetIndicator;
use App\Models\MetIndicatorAppeal;
use App\Models\MetPreliminaryEvaluation;
use App\Services\Metas\MetAppealAttachmentService;
use App\Services\Metas\MetStageAccessService;
use App\Support\Upload\UploadSizeLimit;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

class IndicatorAppealComponent extends Component
{
    use ComputesMetStageCaptureMetrics;
    use InteractsWithMetStageCapture;
    use ResolvesInstrumentIndicatorTypeId;
    use WithFileUploads;

    public int $indicatorTypeId;

    public int $centerId;

    private ?IndicatorType $indicatorTypeModel = null;

    /**
     * @var array<int, array{
     *     clave: string,
     *     nombre: string,
     *     has_appeal: bool,
     *     appeal_id: ?int,
     *     appeal_type: ?string,
     *     motivo: ?string,
     *     archivos: array<int, array{path: string, name: string}>,
     *     meta_efectiva: string,
     *     numerador: string,
     *     denominador: string,
     *     ponderacion: string,
     *     decimales: int,
     *     unidad_medida: ?string
     * }>
     */
    public array $indicators = [];

    public bool $showForm = false;

    public bool $viewOnly = false;

    public ?int $editingIndicatorId = null;

    public ?int $editingAppealId = null;

    public string $appealSignatureLabel = '';

    public string $appealType = '';

    public string $motivo = '';

    /** @var array<int, array{path: string, name: string}> */
    public array $existingAttachments = [];

    public array $newAttachments = [];

    public function mount(?int $indicatorTypeId = null, ?int $centerId = null): void
    {
        $this->indicatorTypeId = $this->resolveInstrumentIndicatorTypeId($indicatorTypeId);
        $this->centerId = (int) $centerId;
        $this->loadIndicators();
    }

    public function loadIndicators(): void
    {
        $this->indicators = [];
        $yearId = $this->currentIndicatorYear()->id;
        $depId = (int) $this->authUser()->cen_hie_dependency_id;

        $appeals = MetIndicatorAppeal::query()
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

        foreach ($this->convenidoConfigurationsForCenter($this->centerId) as $configuration) {
            $indicator = $configuration->metIndicator;
            if (! $indicator) {
                continue;
            }

            $appeal = $appeals->get($indicator->id);
            $prelim = $preliminaries->get($indicator->id);
            $archivos = $appeal?->archivos ?? [];

            $this->indicators[$indicator->id] = [
                'clave' => $indicator->clave,
                'nombre' => $indicator->nombre,
                'has_appeal' => $appeal !== null,
                'appeal_id' => $appeal?->id,
                'appeal_type' => $appeal?->appeal_type?->value,
                'motivo' => $appeal?->motivo,
                'archivos' => is_array($archivos) ? $archivos : [],
                'meta_efectiva' => $configuration->meta_efectiva !== null
                    ? (string) $configuration->meta_efectiva
                    : ($indicator->meta_valor !== null ? (string) $indicator->meta_valor : ''),
                'meta_operador' => $indicator->meta_operador?->value ?? '>=',
                'numerador' => $prelim?->numerador !== null ? (string) $prelim->numerador : '',
                'denominador' => $prelim?->denominador !== null ? (string) $prelim->denominador : '',
                'ponderacion' => $configuration->ponderacion !== null ? (string) $configuration->ponderacion : '0',
                'decimales' => $indicator->decimales ?? 2,
                'unidad_medida' => $indicator->unidad_medida,
            ];
        }
    }

    public function openAppealForm(int $indicatorId): void
    {
        if (! $this->canEditAppeals()) {
            abort(403);
        }

        $this->populateAppealPanel($indicatorId, false);
    }

    public function openAppealView(int $indicatorId): void
    {
        if (! $this->canViewAppeals()) {
            abort(403);
        }

        $this->populateAppealPanel($indicatorId, true);
    }

    public function closeAppealForm(): void
    {
        $this->showForm = false;
        $this->viewOnly = false;
        $this->editingIndicatorId = null;
        $this->editingAppealId = null;
        $this->appealSignatureLabel = '';
        $this->newAttachments = [];
        $this->existingAttachments = [];
    }

    public function removeExistingAttachment(int $index): void
    {
        if (! $this->canEditAppeals() || $this->viewOnly) {
            abort(403);
        }

        $appeal = $this->resolveEditingAppeal();
        if (! $appeal) {
            return;
        }

        $attachmentService = app(MetAppealAttachmentService::class);
        $archivos = $attachmentService->removeAtIndex($appeal->archivos ?? [], $index);

        $appeal->update([
            'archivos' => $archivos,
            'updated_by' => $this->authUser()->id,
        ]);

        $this->existingAttachments = $archivos;
        $this->loadIndicators();
    }

    public function saveAppeal(): void
    {
        if (! $this->canEditAppeals() || $this->viewOnly) {
            abort(403);
        }

        $this->validate([
            'appealType' => ['required', 'in:'.implode(',', array_column(AppealType::cases(), 'value'))],
            'motivo' => ['required', 'string', 'min:10'],
            'newAttachments.*' => ['nullable', 'file', UploadSizeLimit::laravelMaxRule()],
        ]);

        $yearId = $this->currentIndicatorYear()->id;
        $depId = (int) $this->authUser()->cen_hie_dependency_id;
        $userId = $this->authUser()->id;

        $existing = $this->resolveEditingAppeal();

        $attachmentService = app(MetAppealAttachmentService::class);
        $archivos = $attachmentService->appendUploadedFiles(
            $this->newAttachments,
            $yearId,
            $this->centerId,
            (int) $this->editingIndicatorId,
            $existing?->archivos ?? $this->existingAttachments,
        );

        MetIndicatorAppeal::query()->updateOrCreate(
            [
                'met_indicator_id' => $this->editingIndicatorId,
                'cen_center_id' => $this->centerId,
                'year_id' => $yearId,
            ],
            [
                'cen_hie_dependency_id' => $depId,
                'appeal_type' => $this->appealType,
                'motivo' => $this->motivo,
                'archivos' => $archivos,
                'created_by' => $existing?->created_by ?? $userId,
                'submitted_at' => $existing?->submitted_at ?? now(),
                'updated_by' => $userId,
            ],
        );

        $this->closeAppealForm();
        $this->loadIndicators();
        session()->flash('stage_message', 'Apelación guardada correctamente.');
    }

    private function getIndicatorType(): IndicatorType
    {
        return $this->indicatorTypeModel ??= IndicatorType::query()->findOrFail($this->indicatorTypeId);
    }

    public function render(): View
    {
        $etapa = $this->currentEtapaVigente();

        $metIndicators = MetIndicator::query()
            ->with(['formula', 'dataFields'])
            ->whereIn('id', array_keys($this->indicators))
            ->get()
            ->keyBy('id');

        /** @var array<int, array<string, mixed>> $displayRows */
        $displayRows = [];

        foreach ($this->indicators as $indicatorId => $row) {
            $displayRows[$indicatorId] = array_merge(
                $row,
                $this->metricsForEvaluationRow($metIndicators->get((int) $indicatorId), $row),
            );
        }

        return view('livewire.metas-instrument.indicator-appeal', [
            'canEdit' => $this->canEditAppeals(),
            'canView' => $this->canViewAppeals(),
            'appealTypeOptions' => AppealType::options(),
            'etapaVigente' => $etapa,
            'isAppealPeriodClosed' => $etapa !== EtapaVigente::Apelacion,
            'indicatorType' => $this->getIndicatorType(),
            'displayRows' => $displayRows,
        ]);
    }

    private function populateAppealPanel(int $indicatorId, bool $viewOnly): void
    {
        $this->editingIndicatorId = $indicatorId;
        $this->viewOnly = $viewOnly;
        $this->showForm = true;
        $this->newAttachments = [];

        $appeal = MetIndicatorAppeal::query()
            ->with('createdBy.role')
            ->where('met_indicator_id', $indicatorId)
            ->where('cen_center_id', $this->centerId)
            ->where('year_id', $this->currentIndicatorYear()->id)
            ->where('cen_hie_dependency_id', (int) $this->authUser()->cen_hie_dependency_id)
            ->first();

        $this->editingAppealId = $appeal?->id;
        $this->appealType = $appeal?->appeal_type?->value ?? AppealType::CausaExterna->value;
        $this->motivo = $appeal?->motivo ?? '';
        $this->existingAttachments = is_array($appeal?->archivos) ? $appeal->archivos : [];
        $this->appealSignatureLabel = '';

        if ($appeal?->createdBy) {
            $role = optional($appeal->createdBy->role)->display_name
                ?? optional($appeal->createdBy->role)->name
                ?? '';
            $when = $appeal->submitted_at?->format('d-m-Y H:i')
                ?? $appeal->created_at?->format('d-m-Y H:i');
            $this->appealSignatureLabel = trim(
                "Registrada por {$appeal->createdBy->name}".($role !== '' ? " ({$role})" : '').($when ? " · {$when}" : '')
            );
        }
    }

    private function resolveEditingAppeal(): ?MetIndicatorAppeal
    {
        if ($this->editingIndicatorId === null) {
            return null;
        }

        return MetIndicatorAppeal::query()
            ->where('met_indicator_id', $this->editingIndicatorId)
            ->where('cen_center_id', $this->centerId)
            ->where('year_id', $this->currentIndicatorYear()->id)
            ->where('cen_hie_dependency_id', (int) $this->authUser()->cen_hie_dependency_id)
            ->first();
    }

    private function canEditAppeals(): bool
    {
        return app(MetStageAccessService::class)->canEditAppeals(
            $this->authUser(),
            $this->currentEtapaVigente(),
            $this->centerId,
            $this->currentIndicatorYear()->id,
            $this->indicatorTypeId,
        );
    }

    private function canViewAppeals(): bool
    {
        return app(MetStageAccessService::class)->canViewAppeals($this->currentEtapaVigente());
    }
}
