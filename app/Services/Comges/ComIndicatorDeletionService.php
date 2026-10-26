<?php

namespace App\Services\Comges;

use App\Enums\Indicators\IndicatorModuleType;
use App\Helpers\Helper;
use App\Models\ComAction;
use App\Models\ComEvaluation;
use App\Models\ComIndicator;
use App\Models\ComIndicatorSetting;
use App\Models\ComSensitivityDependency;
use App\Models\ComSensitivityTable;
use App\Models\ComVerifiableDocument;
use App\Models\ComVisualization;
use App\Models\Year;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ComIndicatorDeletionService
{
    /**
     * @return array{
     *     items: list<array{key: string, label: string, count: int}>,
     *     total: int
     * }
     */
    public function preview(ComIndicator $indicator): array
    {
        $context = $this->buildContext($indicator);

        $items = [
            ['key' => 'referentes', 'label' => 'Asignaciones de referentes', 'count' => $context['counts']['referentes']],
            ['key' => 'evaluaciones', 'label' => 'Evaluaciones', 'count' => $context['counts']['evaluaciones']],
            ['key' => 'medios_verificacion', 'label' => 'Medios de verificación', 'count' => $context['counts']['medios_verificacion']],
            ['key' => 'sensibilidad', 'label' => 'Tablas de sensibilidad', 'count' => $context['counts']['sensibilidad']],
            ['key' => 'sensibilidad_deps', 'label' => 'Dependencias de sensibilidad', 'count' => $context['counts']['sensibilidad_deps']],
            ['key' => 'acciones', 'label' => 'Acciones', 'count' => $context['counts']['acciones']],
            ['key' => 'visualizaciones', 'label' => 'Visualizaciones por corte', 'count' => $context['counts']['visualizaciones']],
            ['key' => 'cortes', 'label' => 'Cortes del indicador', 'count' => $context['counts']['cortes']],
            ['key' => 'settings', 'label' => 'Configuraciones por SS', 'count' => $context['counts']['settings']],
            ['key' => 'ambitos', 'label' => 'Ámbitos asignados', 'count' => $context['counts']['ambitos']],
            ['key' => 'bitacora', 'label' => 'Registros de bitácora', 'count' => $context['counts']['bitacora']],
            ['key' => 'indicador', 'label' => 'Indicador COMGES', 'count' => 1],
        ];

        $total = collect($items)->sum('count');

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    public function delete(ComIndicator $indicator): void
    {
        $this->assertSessionYear($indicator);

        DB::transaction(function () use ($indicator): void {
            $context = $this->buildContext($indicator);

            $this->deleteVerifiableDocumentFiles($context['verifiable_documents']);

            if ($context['action_ids'] !== []) {
                DB::table('com_verifiable_documents')->whereIn('com_action_id', $context['action_ids'])->delete();
                DB::table('com_evaluations')->whereIn('com_action_id', $context['action_ids'])->delete();
            }

            if ($context['sensitivity_table_ids'] !== []) {
                DB::table('com_sensitivity_dependencies')
                    ->whereIn('com_sensitivity_table_id', $context['sensitivity_table_ids'])
                    ->delete();
            }

            if ($context['action_ids'] !== []) {
                DB::table('com_sensitivity_dependencies')->whereIn('com_action_id', $context['action_ids'])->delete();
                DB::table('com_sensitivity_tables')->whereIn('com_action_id', $context['action_ids'])->delete();
                DB::table('com_actions')->whereIn('id', $context['action_ids'])->delete();
            }

            if ($context['cutoff_ids'] !== []) {
                DB::table('com_visualizations')->whereIn('com_indicator_cutoff_id', $context['cutoff_ids'])->delete();
                DB::table('com_indicator_cutoffs')->whereIn('id', $context['cutoff_ids'])->delete();
            }

            if ($context['setting_ids'] !== []) {
                DB::table('com_indicator_setting_user')->whereIn('com_indicator_setting_id', $context['setting_ids'])->delete();
                DB::table('com_indicator_settings')->whereIn('id', $context['setting_ids'])->delete();
            }

            DB::table('indicator_ambito_assignments')
                ->where('indicatable_type', ComIndicator::class)
                ->where('indicatable_id', $indicator->id)
                ->delete();

            DB::table('indicator_activities')
                ->where('indicator_type', IndicatorModuleType::Comges->value)
                ->where('indicator_id', $indicator->id)
                ->delete();

            $indicator->delete();
        });
    }

    public function assertSessionYear(ComIndicator $indicator): void
    {
        $indicator->loadMissing('comCommitment');

        $sessionYear = Helper::showCurrentYear();

        if (! $sessionYear instanceof Year) {
            abort(404);
        }

        if ((int) $indicator->comCommitment->year_id !== (int) $sessionYear->id) {
            abort(404);
        }
    }

    /**
     * @return array{
     *     cutoff_ids: list<int>,
     *     action_ids: list<int>,
     *     sensitivity_table_ids: list<int>,
     *     setting_ids: list<int>,
     *     verifiable_documents: Collection<int, object>,
     *     counts: array<string, int>
     * }
     */
    private function buildContext(ComIndicator $indicator): array
    {
        $cutoffIds = $indicator->comIndicatorCutoffs()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $actionIds = $cutoffIds === []
            ? []
            : ComAction::query()
                ->whereIn('com_indicator_cutoff_id', $cutoffIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

        $sensitivityTableIds = $actionIds === []
            ? []
            : ComSensitivityTable::query()
                ->whereIn('com_action_id', $actionIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

        $settingIds = $indicator->comIndicatorSettings()
            ->withoutGlobalScope('belongs_to_health_service')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $referentesCount = $settingIds === []
            ? 0
            : (int) ComIndicatorSetting::query()
                ->withoutGlobalScope('belongs_to_health_service')
                ->whereIn('id', $settingIds)
                ->with('users')
                ->get()
                ->sum(fn (ComIndicatorSetting $setting): int => $setting->users->unique('id')->count());

        $evaluacionesCount = $actionIds === []
            ? 0
            : (int) ComEvaluation::query()
                ->withoutGlobalScope('belongs_to_health_service')
                ->whereIn('com_action_id', $actionIds)
                ->count();

        $verifiableDocuments = $actionIds === []
            ? collect()
            : ComVerifiableDocument::query()
                ->withoutGlobalScope('belongs_to_health_service')
                ->whereIn('com_action_id', $actionIds)
                ->get();

        $sensitivityDepsCount = 0;
        if ($actionIds !== [] || $sensitivityTableIds !== []) {
            $sensitivityDepsCount = (int) ComSensitivityDependency::query()
                ->withoutGlobalScope('belongs_to_health_service')
                ->where(function ($query) use ($actionIds, $sensitivityTableIds): void {
                    if ($actionIds !== []) {
                        $query->whereIn('com_action_id', $actionIds);
                    }
                    if ($sensitivityTableIds !== []) {
                        $query->orWhereIn('com_sensitivity_table_id', $sensitivityTableIds);
                    }
                })
                ->count();
        }

        $counts = [
            'referentes' => $referentesCount,
            'evaluaciones' => $evaluacionesCount,
            'medios_verificacion' => $verifiableDocuments->count(),
            'sensibilidad' => count($sensitivityTableIds),
            'sensibilidad_deps' => $sensitivityDepsCount,
            'acciones' => count($actionIds),
            'visualizaciones' => $cutoffIds === []
                ? 0
                : (int) ComVisualization::query()->whereIn('com_indicator_cutoff_id', $cutoffIds)->count(),
            'cortes' => count($cutoffIds),
            'settings' => count($settingIds),
            'ambitos' => (int) $indicator->indicatorAmbitos()->count(),
            'bitacora' => (int) $indicator->indicatorActivities()->count(),
        ];

        return [
            'cutoff_ids' => $cutoffIds,
            'action_ids' => $actionIds,
            'sensitivity_table_ids' => $sensitivityTableIds,
            'setting_ids' => $settingIds,
            'verifiable_documents' => $verifiableDocuments,
            'counts' => $counts,
        ];
    }

    /**
     * @param  Collection<int, object>  $verifiableDocuments
     */
    private function deleteVerifiableDocumentFiles(Collection $verifiableDocuments): void
    {
        $disk = Storage::disk(config('voyager.storage.disk', 'public'));

        foreach ($verifiableDocuments as $document) {
            $archivos = json_decode((string) ($document->archivos ?? ''), false);

            if (! is_array($archivos)) {
                continue;
            }

            foreach ($archivos as $archivo) {
                $path = is_object($archivo) ? ($archivo->download_link ?? null) : null;

                if (! is_string($path) || $path === '') {
                    continue;
                }

                if ($disk->exists($path)) {
                    $disk->delete($path);
                }
            }
        }
    }
}
