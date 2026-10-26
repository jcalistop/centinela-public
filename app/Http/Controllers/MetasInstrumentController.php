<?php

namespace App\Http\Controllers;

use App\Enums\IndicatorType\EtapaVigente;
use App\Enums\Metas\DataFieldInputType;
use App\Helpers\ModuleAccess;
use App\Models\CenCenter;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\MetCenterInclusion;
use App\Models\MetDataField;
use App\Models\MetIndicator;
use App\Models\MetIndicatorAppeal;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetMeasurementUnit;
use App\Models\MetMonthlyData;
use App\Models\User;
use App\Models\Year;
use App\Services\Indicators\IndicatorActivityLogger;
use App\Services\Metas\MetAppealAttachmentService;
use App\Services\Metas\MetEstablishmentIndicatorLoadability;
use App\Services\Metas\MetEstablishmentResponsibleAccess;
use App\Services\Metas\MetFieldSsConfigService;
use App\Services\Metas\MetIndicatorCalculationErrorService;
use App\Services\Metas\MetIndicatorFormulaIssueService;
use App\Services\Metas\MetIndicatorIncompletePeriodService;
use App\Services\Metas\MetIndicatorLoadedDataDeletionService;
use App\Services\Metas\MetIndicatorPeriodicityResolver;
use App\Services\Metas\MetInstrumentDataEntryAccess;
use App\Services\Metas\MetPeriodCaptureXlsxNormalizer;
use App\Services\Metas\MetRecalculationDispatcher;
use App\Services\Metas\MetRecalculationScope;
use App\Services\Metas\MetStageAccessService;
use App\Support\Metas\MetExcelDecimal;
use App\Support\Upload\UploadSizeLimit;
use Illuminate\Contracts\View\View;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\SimpleExcel\SimpleExcelReader;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MetasInstrumentController extends Controller
{
    /** @var array<string, int> */
    private const MESES = [
        'Ene' => 1,
        'Feb' => 2,
        'Mar' => 3,
        'Abr' => 4,
        'May' => 5,
        'Jun' => 6,
        'Jul' => 7,
        'Ago' => 8,
        'Sep' => 9,
        'Oct' => 10,
        'Nov' => 11,
        'Dic' => 12,
    ];

    public function index(IndicatorType $indicatorType): View
    {
        $this->assertMetasInstrumentIndicatorType($indicatorType);

        $user = $this->authUser();

        if (! ModuleAccess::canAccess($indicatorType->route)) {
            abort(403, 'Modulo no disponible para su Servicio de Salud.');
        }

        $setting = IndicatorTypeSetting::getData($indicatorType->id);
        $roleName = $user->role->name;

        return view('centinela.metas-instrument.index', compact(
            'indicatorType',
            'setting',
            'roleName',
        ));
    }

    public function configureEstablishments(IndicatorType $indicatorType): View
    {
        $this->assertMetasInstrumentIndicatorType($indicatorType);

        $user = $this->authUser();

        if (! ModuleAccess::canAccess($indicatorType->route)) {
            abort(403, 'Modulo no disponible para su Servicio de Salud.');
        }

        if (! $user->hasRole(['control-gestion', 'admin'])) {
            abort(403);
        }

        $setting = IndicatorTypeSetting::getData($indicatorType->id);
        $roleName = $user->role->name;

        return view('centinela.metas-instrument.configure-establishments', compact(
            'indicatorType',
            'setting',
            'roleName',
        ));
    }

    public function configureEstablishmentIndicators(IndicatorType $indicatorType, CenCenter $center): View
    {
        $this->assertMetasInstrumentIndicatorType($indicatorType);

        $user = $this->authUser();
        $yearId = $this->currentIndicatorYearModel()->id;

        if (! ModuleAccess::canAccess($indicatorType->route)) {
            abort(403, 'Modulo no disponible para su Servicio de Salud.');
        }

        $canAccess = $user->hasRole(['control-gestion', 'admin']);

        if (! $canAccess) {
            $canAccess = app(MetEstablishmentResponsibleAccess::class)->canViewEstablishmentIndicators(
                $user,
                (int) $center->id,
                $yearId,
                (int) $indicatorType->id,
            );
        }

        if (! $canAccess) {
            abort(403);
        }

        if (
            ! $user->hasRole('admin')
            && (int) $center->cen_hie_dependency_id !== (int) $user->cen_hie_dependency_id
        ) {
            abort(403);
        }

        $setting = IndicatorTypeSetting::getData($indicatorType->id);
        $roleName = $user->role->name;

        return view('centinela.metas-instrument.configure-establishment-indicators', compact(
            'indicatorType',
            'setting',
            'roleName',
            'center',
        ));
    }

    public function establishmentResults(IndicatorType $indicatorType, CenCenter $center): View
    {
        $this->assertMetasInstrumentIndicatorType($indicatorType);

        $user = $this->authUser();

        if (! ModuleAccess::canAccess($indicatorType->route)) {
            abort(403, 'Modulo no disponible para su Servicio de Salud.');
        }

        $setting = IndicatorTypeSetting::getData($indicatorType->id);
        $roleName = $user->role->name;

        if ($user->role->name !== 'parametrizador') {
            if ((int) $center->cen_hie_dependency_id !== (int) $user->cen_hie_dependency_id) {
                abort(403);
            }
        }

        $measurementUnit = null;
        if (request()->filled('unidad')) {
            $year = session()->get('indicator_year');
            abort_unless($year instanceof Year, 500);

            $measurementUnit = MetMeasurementUnit::query()
                ->where('id', request()->integer('unidad'))
                ->where('cen_center_id', $center->id)
                ->where('year_id', $year->id)
                ->where('cen_hie_dependency_id', (int) $user->cen_hie_dependency_id)
                ->active()
                ->firstOrFail();
        }

        return view('centinela.metas-instrument.establishment-results', compact(
            'indicatorType',
            'setting',
            'roleName',
            'center',
            'measurementUnit',
        ));
    }

    public function downloadAppealAttachment(
        IndicatorType $indicatorType,
        MetIndicatorAppeal $appeal,
        int $attachmentIndex,
    ): BinaryFileResponse|StreamedResponse {
        $this->assertMetasInstrumentIndicatorType($indicatorType);

        $user = $this->authUser();
        $setting = IndicatorTypeSetting::getData($indicatorType->id);

        if (! app(MetStageAccessService::class)->canDownloadAppealAttachments($setting->etapa_vigente)) {
            abort(403);
        }

        if (
            $user->role->name !== 'admin'
            && (int) $appeal->cen_hie_dependency_id !== (int) $user->cen_hie_dependency_id
        ) {
            abort(403);
        }

        $appeal->loadMissing('metIndicator');
        if (
            $appeal->metIndicator === null
            || (int) $appeal->metIndicator->indicator_type_id !== (int) $indicatorType->id
        ) {
            abort(404);
        }

        $attachment = app(MetAppealAttachmentService::class)
            ->resolveAttachmentForDownload($appeal, $attachmentIndex);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');

        return response()->download(
            $disk->path($attachment['path']),
            $attachment['name'],
        );
    }

    public function indicatorCarga(IndicatorType $indicatorType, MetIndicator $indicator): View
    {
        $this->assertMetasInstrumentIndicatorType($indicatorType);
        $this->assertIndicatorBelongsToInstrument($indicator, $indicatorType);
        $this->authorizeDataEntryForIndicator($indicator);

        $setting = IndicatorTypeSetting::getData($indicatorType->id);
        $yearId = $this->currentIndicatorYearModel()->id;

        $centros = $this->getCentrosParaCargaIndicador($indicator, $yearId);

        $adosadoCargaMeta = [];
        if (! $indicatorType->usesInternalUnitMeasurement() && $centros->isNotEmpty()) {
            $adosadoCargaMeta = app(MetEstablishmentIndicatorLoadability::class)->adosadoMadreMetaByAdosadoCenterId(
                $centros->pluck('id'),
                $yearId,
                (int) $this->authUser()->cen_hie_dependency_id,
                (int) $indicatorType->id,
            );
        }

        $ultimoMesPorCentro = [];
        if ($centros->isNotEmpty()) {
            $q = MetMonthlyData::query()
                ->join('met_data_fields', 'met_monthly_data.met_data_field_id', '=', 'met_data_fields.id')
                ->where('met_data_fields.met_indicator_id', $indicator->id)
                ->whereIn('met_monthly_data.cen_center_id', $centros->pluck('id'))
                ->where('met_monthly_data.year_id', $yearId)
                ->where('met_monthly_data.cen_hie_dependency_id', $this->authUser()->cen_hie_dependency_id)
                ->whereNotNull('met_monthly_data.valor');

            if (! $indicatorType->usesInternalUnitMeasurement()) {
                $q->whereNull('met_monthly_data.met_measurement_unit_id');
            }

            $q->selectRaw('met_monthly_data.cen_center_id, MAX(met_monthly_data.mes) as ultimo_mes')
                ->groupBy('met_monthly_data.cen_center_id')
                ->get()
                ->each(function ($row) use (&$ultimoMesPorCentro): void {
                    $ultimoMesPorCentro[(int) $row->cen_center_id] = (int) $row->ultimo_mes;
                });
        }

        $indicator->load(['dataFields', 'formula']);

        $formulaIssue = app(MetIndicatorFormulaIssueService::class)->issueForIndicator($indicator);

        $fieldSsConfigService = app(MetFieldSsConfigService::class);
        $ssId = (int) $this->authUser()->cen_hie_dependency_id;
        $fieldSsConfigStatus = $fieldSsConfigService->configurationStatusForIndicator(
            $indicator,
            $ssId,
            $yearId,
        );
        $allowsDataEntryHub = $this->authUser()->hasRole(['control-gestion', 'admin'])
            || $fieldSsConfigService->allowsReferenteDataEntry($indicator, $ssId, $yearId);

        $cargaPeriodicidad = app(MetIndicatorPeriodicityResolver::class)
            ->resolveForCarga($indicator, $ssId, $yearId)->value;

        $erroresCalculoPorCentro = collect();
        if ($centros->isNotEmpty()) {
            $erroresCalculoPorCentro = app(MetIndicatorCalculationErrorService::class)->errorsByCenterId(
                (int) $indicator->id,
                $ssId,
                $yearId,
                $centros->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            );
        }

        $periodosIncompletosPorCentro = collect();
        if ($centros->isNotEmpty()) {
            $periodosIncompletosPorCentro = app(MetIndicatorIncompletePeriodService::class)->incompletePeriodsByCenterId(
                (int) $indicator->id,
                $ssId,
                $yearId,
                $centros->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            );
        }

        $deletionService = app(MetIndicatorLoadedDataDeletionService::class);
        $canDeleteLoadedData = $this->authUser()->hasRole(['control-gestion', 'admin'])
            && app(MetStageAccessService::class)->canWriteMonthlyData($setting->etapa_vigente);
        $hasLoadedData = $deletionService->hasLoadedData($indicator, $ssId, $yearId);

        $effectiveFieldTypeRows = $fieldSsConfigService->effectiveFieldTypeRowsForIndicator(
            $indicator,
            $ssId,
            $yearId,
        );

        return view('centinela.metas-instrument.indicador-carga', compact(
            'indicatorType',
            'setting',
            'indicator',
            'centros',
            'adosadoCargaMeta',
            'ultimoMesPorCentro',
            'fieldSsConfigStatus',
            'allowsDataEntryHub',
            'cargaPeriodicidad',
            'erroresCalculoPorCentro',
            'periodosIncompletosPorCentro',
            'formulaIssue',
            'canDeleteLoadedData',
            'hasLoadedData',
            'effectiveFieldTypeRows',
        ));
    }

    public function deleteIndicatorLoadedData(IndicatorType $indicatorType, MetIndicator $indicator): RedirectResponse
    {
        $this->assertMetasInstrumentIndicatorType($indicatorType);
        $this->assertIndicatorBelongsToInstrument($indicator, $indicatorType);

        if (! $this->authUser()->hasRole(['control-gestion', 'admin'])) {
            abort(403);
        }

        $setting = IndicatorTypeSetting::getData($indicatorType->id);

        if (! app(MetStageAccessService::class)->canWriteMonthlyData($setting->etapa_vigente)) {
            return back()
                ->with([
                    'message' => 'Instrumento cerrado para carga en la etapa actual ('.$setting->etapa_vigente->value.'). La eliminación solo está disponible en etapa AUTOEVALUACION.',
                    'alert-type' => 'error',
                ])
                ->withFragment('tab-carga-datos');
        }

        $yearId = $this->currentIndicatorYearModel()->id;
        $ssId = (int) $this->authUser()->cen_hie_dependency_id;

        $deletionService = app(MetIndicatorLoadedDataDeletionService::class);

        if (! $deletionService->hasLoadedData($indicator, $ssId, $yearId)) {
            return back()
                ->with([
                    'message' => 'Este indicador no tiene datos cargados para eliminar en su Servicio de Salud.',
                    'alert-type' => 'warning',
                ])
                ->withFragment('tab-carga-datos');
        }

        $counts = $deletionService->deleteForIndicatorScope(
            $indicator,
            (int) $indicatorType->id,
            $ssId,
            $yearId,
        );

        app(MetRecalculationDispatcher::class)->scheduleFullAutoevaluacion(
            $yearId,
            $ssId,
            (int) $indicatorType->id,
        );

        return back()
            ->with([
                'message' => "Se eliminaron {$counts['monthly_deleted']} registro(s) de carga y {$counts['results_deleted']} resultado(s) calculados para el indicador {$indicator->clave}. Se encoló un recálculo del instrumento.",
                'alert-type' => 'success',
            ])
            ->withFragment('tab-carga-datos');
    }

    public function show(IndicatorType $indicatorType, MetIndicator $indicator, CenCenter $center): View|RedirectResponse
    {
        $this->assertMetasInstrumentIndicatorType($indicatorType);
        $this->assertIndicatorBelongsToInstrument($indicator, $indicatorType);

        $yearId = $this->currentIndicatorYearModel()->id;

        if ($indicator->year_id !== $yearId) {
            abort(404);
        }

        if ((int) $center->cen_hie_dependency_id !== (int) $this->authUser()->cen_hie_dependency_id) {
            abort(403);
        }

        $this->authorizeEstablishmentDataEntry($indicator, $center, $indicatorType);

        if ($indicatorType->usesInternalUnitMeasurement()) {
            return redirect()->route('metas.instrument.indicador.center.units', [
                'indicatorType' => $indicatorType,
                'indicator' => $indicator,
                'center' => $center,
            ]);
        }

        $this->authorizeEstablishmentCenterScope($indicator, $center, $indicatorType, $yearId);

        return view('centinela.metas-instrument.indicador-detalle', compact('indicatorType', 'indicator', 'center'));
    }

    public function centerUnits(IndicatorType $indicatorType, MetIndicator $indicator, CenCenter $center): View
    {
        $this->assertMetasInstrumentIndicatorType($indicatorType);
        $this->assertIndicatorBelongsToInstrument($indicator, $indicatorType);
        abort_unless($indicatorType->usesInternalUnitMeasurement(), 404);

        $yearId = $this->currentIndicatorYearModel()->id;

        if ($indicator->year_id !== $yearId) {
            abort(404);
        }

        if ((int) $center->cen_hie_dependency_id !== (int) $this->authUser()->cen_hie_dependency_id) {
            abort(403);
        }

        $this->authorizeEstablishmentDataEntry($indicator, $center, $indicatorType);

        $units = MetMeasurementUnit::query()
            ->forCenterYear($center->id, $yearId)
            ->active()
            ->orderBy('codigo')
            ->get();

        return view('centinela.metas-instrument.indicador-centro-unidades', compact(
            'indicatorType',
            'indicator',
            'center',
            'units',
        ));
    }

    public function showUnit(IndicatorType $indicatorType, MetIndicator $indicator, CenCenter $center, MetMeasurementUnit $measurementUnit): View
    {
        $this->assertMetasInstrumentIndicatorType($indicatorType);
        $this->assertIndicatorBelongsToInstrument($indicator, $indicatorType);
        abort_unless($indicatorType->usesInternalUnitMeasurement(), 404);

        $yearId = $this->currentIndicatorYearModel()->id;

        if ($indicator->year_id !== $yearId) {
            abort(404);
        }

        if ((int) $center->cen_hie_dependency_id !== (int) $this->authUser()->cen_hie_dependency_id) {
            abort(403);
        }

        if (
            (int) $measurementUnit->cen_center_id !== (int) $center->id
            || (int) $measurementUnit->year_id !== $yearId
        ) {
            abort(404);
        }

        $this->authorizeEstablishmentDataEntry($indicator, $center, $indicatorType);

        $user = $this->authUser();

        if (! $user->hasRole(['control-gestion', 'admin'])) {
            $isIncluded = MetCenterInclusion::query()
                ->where('cen_center_id', $center->id)
                ->where('year_id', $yearId)
                ->where('indicator_type_id', $indicatorType->id)
                ->exists();

            if (! $isIncluded) {
                abort(403);
            }

            $aplica = MetIndicatorConfiguration::query()
                ->where('met_indicator_id', $indicator->id)
                ->where('cen_center_id', $center->id)
                ->where('year_id', $yearId)
                ->where('met_measurement_unit_id', $measurementUnit->id)
                ->value('aplica');

            if ($aplica !== null && ! $aplica) {
                abort(403);
            }
        }

        return view('centinela.metas-instrument.indicador-unidad-detalle', compact(
            'indicatorType',
            'indicator',
            'center',
            'measurementUnit',
        ));
    }

    public function downloadDataDetail(Request $request, IndicatorType $indicatorType, MetIndicator $indicator): BinaryFileResponse
    {
        $this->assertMetasInstrumentIndicatorType($indicatorType);
        $this->assertIndicatorBelongsToInstrument($indicator, $indicatorType);

        $validated = $request->validate([
            'centers' => ['required', 'array', 'min:1'],
            'centers.*' => ['integer', 'distinct'],
        ]);

        $yearId = $this->currentIndicatorYearModel()->id;

        if ($indicator->year_id !== $yearId) {
            abort(404);
        }

        $centros = $this->resolveCentersForDataDetailDownload(
            $indicatorType,
            $indicator,
            collect($validated['centers'])->map(fn (mixed $id): int => (int) $id)->unique()->values(),
            $yearId,
        );

        $exportPrefix = Str::studly((string) $indicatorType->route);
        $nombreArchivo = date('YmdHi').'_detalle_'.$exportPrefix.'_'.$indicator->clave.'_'.$this->currentIndicatorYearModel()->ano.'.xlsx';

        return $this->exportIndicatorDataDetailXlsx($indicatorType, $indicator, $centros, $nombreArchivo);
    }

    public function downloadTemplate(IndicatorType $indicatorType, MetIndicator $indicator): BinaryFileResponse
    {
        $this->assertMetasInstrumentIndicatorType($indicatorType);

        if (! $this->authUser()->hasRole(['referente', 'control-gestion', 'admin'])) {
            abort(403);
        }

        $this->assertIndicatorBelongsToInstrument($indicator, $indicatorType);
        $this->authorizeDataEntryForIndicator($indicator);
        $this->authorizeReferenteFieldSsConfigForDataEntry($indicator);

        $yearId = $this->currentIndicatorYearModel()->id;
        $centrosReferente = $this->getCentrosParaCargaIndicador($indicator, $yearId, forExcel: true);

        $exportPrefix = Str::studly((string) $indicatorType->route);
        $nombreArchivo = date('YmdHi').'_'.$exportPrefix.'_'.$indicator->clave.'_'.$this->currentIndicatorYearModel()->ano.'.xlsx';

        return $this->exportIndicatorDataDetailXlsx($indicatorType, $indicator, $centrosReferente, $nombreArchivo);
    }

    public function import(Request $request, IndicatorType $indicatorType, MetIndicator $indicator): RedirectResponse
    {
        $this->assertMetasInstrumentIndicatorType($indicatorType);

        if (! $this->authUser()->hasRole(['referente', 'control-gestion', 'admin'])) {
            abort(403);
        }

        $this->assertIndicatorBelongsToInstrument($indicator, $indicatorType);
        $this->authorizeDataEntryForIndicator($indicator);
        $this->authorizeReferenteFieldSsConfigForDataEntry($indicator);

        $setting = IndicatorTypeSetting::getData($indicatorType->id);

        if ($setting->etapa_vigente !== EtapaVigente::Autoevaluacion) {
            return back()
                ->with([
                    'message' => 'Instrumento cerrado para carga en la etapa actual ('.$setting->etapa_vigente->value.'). El import solo está disponible en etapa AUTOEVALUACION.',
                    'alert-type' => 'error',
                ])
                ->withFragment('tab-carga-datos');
        }

        $request->validate([
            'archivo' => [
                'required',
                'file',
                'mimes:xlsx',
                'max:'.UploadSizeLimit::importSpreadsheetMaxKilobytes(),
            ],
        ]);

        $yearId = $this->currentIndicatorYearModel()->id;
        $ssId = (int) $this->authUser()->cen_hie_dependency_id;
        $indicator->load('dataFields');

        $centrosReferente = $this->getCentrosParaCargaIndicador($indicator, $yearId, forExcel: true);

        /** @var array<string, CenCenter> $centroLookup */
        $centroLookup = $centrosReferente->keyBy('codigo')->all();

        $fieldSsConfigService = app(MetFieldSsConfigService::class);
        $effectiveFields = $fieldSsConfigService->applyEffectiveTypes($indicator->dataFields, $ssId, $yearId);

        /** @var array<string, MetDataField> $fieldLookup */
        $fieldLookup = [];
        foreach ($effectiveFields as $field) {
            $fieldLookup["{$indicator->clave}:{$field->variable_key}"] = $field;
        }

        $path = $request->file('archivo')->store('temp');
        $fullPath = storage_path('app/'.$path);

        $importErrors = [];
        $processedCount = 0;

        /** @var array<string, array{centerId: int, measurementUnitId: ?int}> $affectedScopes */
        $affectedScopes = [];

        $requiresUnit = $indicatorType->usesInternalUnitMeasurement();

        $unitLookupByCenterCodigo = [];

        if ($requiresUnit) {
            foreach ($centrosReferente as $c) {
                $unitLookupByCenterCodigo[$c->codigo] = MetMeasurementUnit::query()
                    ->where('cen_center_id', $c->id)
                    ->where('year_id', $yearId)
                    ->where('cen_hie_dependency_id', $this->authUser()->cen_hie_dependency_id)
                    ->get()
                    ->keyBy('codigo')
                    ->all();
            }
        }

        $hasBaselineFields = $effectiveFields->contains(
            fn (MetDataField $field): bool => $field->input_type === DataFieldInputType::LineaBase
        );

        $activityLogger = app(IndicatorActivityLogger::class);
        $importedPeriodTouches = [];
        $activityLogger->withoutAutomaticLogging(function () use (
            $fullPath,
            $centroLookup,
            $fieldLookup,
            $yearId,
            $ssId,
            &$importErrors,
            &$processedCount,
            &$affectedScopes,
            $indicator,
            $requiresUnit,
            $unitLookupByCenterCodigo,
            $hasBaselineFields,
            &$importedPeriodTouches,
        ): void {
            $this->processSheet($fullPath, 'Datos_Mensuales', $centroLookup, $fieldLookup, $yearId, $ssId, $importErrors, $processedCount, $affectedScopes, false, $indicator->clave, $requiresUnit, $unitLookupByCenterCodigo, $indicator, $importedPeriodTouches);

            if ($hasBaselineFields) {
                $this->processSheet($fullPath, 'Linea_Base', $centroLookup, $fieldLookup, $yearId, $ssId, $importErrors, $processedCount, $affectedScopes, true, $indicator->clave, $requiresUnit, $unitLookupByCenterCodigo, $indicator, $importedPeriodTouches);
            }
        });

        @unlink($fullPath);

        if ($processedCount > 0) {
            $activityLogger->logBulkDataImport(
                (int) $indicator->id,
                $ssId,
                $processedCount,
                (int) $this->authUser()->id,
            );

            $this->scheduleRecalculationAfterImport(
                $affectedScopes,
                $yearId,
                $ssId,
                (int) $indicatorType->id,
                (int) $indicator->id,
            );
        }

        $message = "Importación completada: {$processedCount} registro(s) procesado(s).";
        if ($processedCount > 0) {
            $message .= ' Se encoló recálculo de resultados para los establecimientos afectados.';
        }
        if (! empty($importErrors)) {
            $message .= ' Se encontraron '.count($importErrors).' error(es).';
        }

        return back()
            ->with([
                'message' => $message,
                'alert-type' => empty($importErrors) ? 'success' : 'warning',
                'import_errors' => $importErrors,
            ])
            ->withFragment('tab-carga-datos');
    }

    /**
     * @param  array<string, CenCenter>  $centroLookup
     * @param  array<string, MetDataField>  $fieldLookup
     * @param  array<int, string>  $importErrors
     * @param  array<string, array{centerId: int, measurementUnitId: ?int}>  $affectedScopes
     * @param  array<string, array<string, MetMeasurementUnit>>  $unitLookupByCenterCodigo
     * @param  array<string, array{centerId: int, mes: int, measurementUnitId: ?int}>  $importedPeriodTouches
     */
    private function processSheet(
        string $path,
        string $sheetName,
        array $centroLookup,
        array $fieldLookup,
        int $yearId,
        int $ssId,
        array &$importErrors,
        int &$processedCount,
        array &$affectedScopes,
        bool $isBaseline,
        string $expectedIndicadorClave,
        bool $requiresUnit = false,
        array $unitLookupByCenterCodigo = [],
        ?MetIndicator $indicator = null,
        array &$importedPeriodTouches = [],
    ): void {
        try {
            $reader = SimpleExcelReader::create($path)->fromSheetName($sheetName);

            $rowNumber = 1;
            $reader->getRows()->each(function (array $row) use (
                $centroLookup,
                $fieldLookup,
                $yearId,
                $ssId,
                &$importErrors,
                &$processedCount,
                &$affectedScopes,
                $isBaseline,
                $sheetName,
                &$rowNumber,
                $expectedIndicadorClave,
                $requiresUnit,
                $unitLookupByCenterCodigo,
                &$importedPeriodTouches,
            ): void {
                $rowNumber++;
                if (! isset($row['componente']) && isset($row['rol'])) {
                    $row['componente'] = $row['rol'];
                }
                $centroCodigo = trim((string) ($row['centro_codigo'] ?? ''));
                $indicadorClave = trim((string) ($row['indicador_clave'] ?? ''));
                $variableKey = trim((string) ($row['variable_key'] ?? ''));

                if ($centroCodigo === '' && $indicadorClave === '' && $variableKey === '') {
                    return;
                }

                if ($indicadorClave !== $expectedIndicadorClave) {
                    $importErrors[] = "[{$sheetName} fila {$rowNumber}] El indicador de la fila ('{$indicadorClave}') no coincide con el indicador de esta carga ('{$expectedIndicadorClave}').";

                    return;
                }

                if (! isset($centroLookup[$centroCodigo])) {
                    $importErrors[] = "[{$sheetName} fila {$rowNumber}] Centro '{$centroCodigo}' no autorizado o no incluido en la medición.";

                    return;
                }

                $centro = $centroLookup[$centroCodigo];

                $measurementUnitId = null;

                if ($requiresUnit) {
                    $unidadCodigo = trim((string) ($row['unidad_codigo'] ?? ''));

                    if ($unidadCodigo === '') {
                        $importErrors[] = "[{$sheetName} fila {$rowNumber}] La columna unidad_codigo es obligatoria para este instrumento.";

                        return;
                    }

                    $unitMap = $unitLookupByCenterCodigo[$centroCodigo] ?? [];

                    if (! isset($unitMap[$unidadCodigo])) {
                        $importErrors[] = "[{$sheetName} fila {$rowNumber}] Unidad '{$unidadCodigo}' inexistente o no pertenece al centro '{$centroCodigo}'.";

                        return;
                    }

                    $unitModel = $unitMap[$unidadCodigo];

                    if (! $unitModel->activo) {
                        $importErrors[] = "[{$sheetName} fila {$rowNumber}] La unidad '{$unidadCodigo}' está inactiva; no se puede importar.";

                        return;
                    }

                    $measurementUnitId = (int) $unitModel->id;
                }

                $fieldKey = "{$indicadorClave}:{$variableKey}";
                if (! isset($fieldLookup[$fieldKey])) {
                    $importErrors[] = "[{$sheetName} fila {$rowNumber}] Combinación indicador '{$indicadorClave}' / variable '{$variableKey}' no encontrada o no asignada.";

                    return;
                }

                $field = $fieldLookup[$fieldKey];
                $inputType = $field->resolveInputType($ssId, $yearId);

                if ($isBaseline) {
                    if ($inputType !== DataFieldInputType::LineaBase) {
                        $importErrors[] = "[{$sheetName} fila {$rowNumber}] La variable '{$variableKey}' no es de línea base para su Servicio de Salud; use la hoja Datos_Mensuales.";

                        return;
                    }

                    $rawValor = $row['valor'] ?? null;
                    $parsedValor = MetExcelDecimal::tryParseImported($rawValor);

                    if (! $parsedValor['valid']) {
                        $importErrors[] = "[{$sheetName} fila {$rowNumber}] Valor no numérico en columna 'valor': '{$rawValor}'.";

                        return;
                    }

                    if ($parsedValor['value'] === null) {
                        return;
                    }

                    if (! $this->persistImportedMonthlyValue(
                        $field,
                        $centro,
                        $yearId,
                        $ssId,
                        null,
                        $parsedValor['value'],
                        $measurementUnitId,
                        $sheetName,
                        $rowNumber,
                        $importErrors,
                        $processedCount,
                        $affectedScopes,
                    )) {
                        return;
                    }

                    return;
                }

                if ($inputType === DataFieldInputType::LineaBase) {
                    $importErrors[] = "[{$sheetName} fila {$rowNumber}] La variable '{$variableKey}' es de línea base para su Servicio de Salud; use la hoja Linea_Base.";

                    return;
                }

                $allowedMonths = $inputType->monthNumbers();

                foreach (self::MESES as $mesLabel => $mesNum) {
                    $rawValor = $row[$mesLabel] ?? null;
                    $monthApplies = in_array($mesNum, $allowedMonths, true);

                    if (! $monthApplies && $this->shouldIgnoreImportedMonthValue($rawValor)) {
                        continue;
                    }

                    $parsedValor = MetExcelDecimal::tryParseImported($rawValor);

                    if (! $parsedValor['valid']) {
                        $importErrors[] = "[{$sheetName} fila {$rowNumber}] Valor no numérico en columna '{$mesLabel}': '{$rawValor}'.";

                        continue;
                    }

                    if ($parsedValor['value'] === null) {
                        continue;
                    }

                    if (! $monthApplies) {
                        $importErrors[] = "[{$sheetName} fila {$rowNumber}] El mes '{$mesLabel}' no corresponde al tipo de entrada efectivo de la variable '{$variableKey}' ({$inputType->label()}).";

                        continue;
                    }

                    $this->persistImportedMonthlyValue(
                        $field,
                        $centro,
                        $yearId,
                        $ssId,
                        $mesNum,
                        $parsedValor['value'],
                        $measurementUnitId,
                        $sheetName,
                        $rowNumber,
                        $importErrors,
                        $processedCount,
                        $affectedScopes,
                        $importedPeriodTouches,
                    );
                }
            });
        } catch (\Throwable) {
            return;
        }

        if (! $isBaseline && $indicator !== null && $importedPeriodTouches !== []) {
            $normalizer = app(MetPeriodCaptureXlsxNormalizer::class);
            foreach ($importedPeriodTouches as $touch) {
                $normalizer->zeroFillMissingFieldsInDatabase(
                    $indicator,
                    $touch['centerId'],
                    $touch['mes'],
                    $touch['measurementUnitId'],
                    $ssId,
                    $yearId,
                    $importErrors,
                    $processedCount,
                    $affectedScopes,
                    $sheetName,
                    0,
                );
            }
        }
    }

    /**
     * @param  array<int, string>  $importErrors
     * @param  array<string, array{centerId: int, measurementUnitId: ?int}>  $affectedScopes
     * @param  array<string, array{centerId: int, mes: int, measurementUnitId: ?int}>  $importedPeriodTouches
     */
    private function persistImportedMonthlyValue(
        MetDataField $field,
        CenCenter $centro,
        int $yearId,
        int $ssId,
        ?int $mes,
        float $valor,
        ?int $measurementUnitId,
        string $sheetName,
        int $rowNumber,
        array &$importErrors,
        int &$processedCount,
        array &$affectedScopes,
        array &$importedPeriodTouches = [],
    ): bool {
        try {
            MetMonthlyData::updateOrCreate(
                [
                    'met_data_field_id' => $field->id,
                    'cen_center_id' => $centro->id,
                    'cen_hie_dependency_id' => $ssId,
                    'year_id' => $yearId,
                    'mes' => $mes,
                    'met_measurement_unit_id' => $measurementUnitId,
                ],
                ['valor' => $valor]
            );
            $processedCount++;

            $scopeKey = $centro->id.':'.(int) ($measurementUnitId ?? 0);
            $affectedScopes[$scopeKey] = [
                'centerId' => (int) $centro->id,
                'measurementUnitId' => $measurementUnitId,
            ];

            if ($mes !== null) {
                $periodKey = $centro->id.':'.$mes.':'.(int) ($measurementUnitId ?? 0);
                $importedPeriodTouches[$periodKey] = [
                    'centerId' => (int) $centro->id,
                    'mes' => $mes,
                    'measurementUnitId' => $measurementUnitId,
                ];
            }

            return true;
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();
            $importErrors[] = "[{$sheetName} fila {$rowNumber}] {$message}";

            return false;
        }
    }

    /**
     * @param  array<string, array{centerId: int, measurementUnitId: ?int}>  $affectedScopes
     */
    private function scheduleRecalculationAfterImport(
        array $affectedScopes,
        int $yearId,
        int $dependencyId,
        int $indicatorTypeId,
        int $metIndicatorId,
    ): void {
        $dispatcher = app(MetRecalculationDispatcher::class);

        foreach ($affectedScopes as $scope) {
            $dispatcher->scheduleAutoevaluacionScope(
                new MetRecalculationScope(
                    yearId: $yearId,
                    dependencyId: $dependencyId,
                    indicatorTypeId: $indicatorTypeId,
                    centerId: $scope['centerId'],
                    metIndicatorId: $metIndicatorId,
                    measurementUnitId: $scope['measurementUnitId'],
                ),
            );
        }
    }

    /**
     * @return Collection<int, CenCenter>
     */
    private function getCentrosParaCargaDatos(int $yearId, ?int $indicatorTypeId = null): Collection
    {
        $query = CenCenter::hieFilter()->orderBy('nombre_corto');

        if (! $this->authUser()->hasRole('control-gestion')) {
            $query->whereHas('metCenterInclusions', function ($q) use ($yearId, $indicatorTypeId): void {
                $q->where('year_id', $yearId);
                if ($indicatorTypeId !== null) {
                    $q->where('indicator_type_id', $indicatorTypeId);
                }
            });
        }

        return $query->get();
    }

    /**
     * @return Collection<int, CenCenter>
     */
    private function getCentrosParaCargaIndicador(MetIndicator $indicator, int $yearId, bool $forExcel = false): Collection
    {
        $indicator->loadMissing('indicatorType');

        $ssId = (int) $this->authUser()->cen_hie_dependency_id;
        $loadability = app(MetEstablishmentIndicatorLoadability::class);

        $base = $forExcel
            ? CenCenter::hieFilter()->orderBy('codigo')->get()
            : $this->getCentrosParaCargaDatos($yearId, (int) $indicator->indicator_type_id);

        if ($indicator->indicatorType?->usesInternalUnitMeasurement()) {
            // Para instrumentos internal_unit: mostrar centros incluidos en MetCenterInclusion,
            // aunque aún no tengan configuración por unidad. Así el usuario puede llegar a la
            // página "Unidades / carga", crear las unidades, y luego configurar por unidad.
            $aplicaCenterIds = MetCenterInclusion::query()
                ->where('cen_hie_dependency_id', $ssId)
                ->where('year_id', $yearId)
                ->where('indicator_type_id', $indicator->indicator_type_id)
                ->pluck('cen_center_id');

            $centros = $base->filter(fn (CenCenter $c): bool => $aplicaCenterIds->contains($c->id))->values();

            return $forExcel
                ? $centros->sortBy(fn (CenCenter $c): string => (string) $c->codigo)->values()
                : $centros;
        }

        $motherApplicableIds = MetIndicatorConfiguration::query()
            ->where('cen_hie_dependency_id', $ssId)
            ->where('met_indicator_id', $indicator->id)
            ->where('year_id', $yearId)
            ->where('aplica', true)
            ->whereNull('met_measurement_unit_id')
            ->pluck('cen_center_id')
            ->unique()
            ->values();

        $mothersInScope = $forExcel
            ? $motherApplicableIds
            : $motherApplicableIds->intersect($base->pluck('id'))->values();

        $allowedIds = $loadability->allowedEstablishmentCenterIdsIncludingAdosados(
            $mothersInScope,
            $yearId,
            $ssId,
            (int) $indicator->indicator_type_id,
        );

        $filtered = $base->filter(fn (CenCenter $c): bool => $allowedIds->contains($c->id))->values();

        $missingIds = $allowedIds->diff($filtered->pluck('id'));
        if ($missingIds->isNotEmpty()) {
            $extra = CenCenter::query()
                ->where('cen_hie_dependency_id', $ssId)
                ->whereIn('id', $missingIds)
                ->orderBy($forExcel ? 'codigo' : 'nombre_corto')
                ->get();
            $filtered = $filtered->concat($extra);
        }

        $allCenters = $filtered->unique('id')->values();

        $mothersOrderedIds = $base->filter(fn (CenCenter $c): bool => $mothersInScope->contains($c->id))
            ->pluck('id')
            ->values();

        $ordered = $loadability->orderCentersMotherThenAdosados(
            $allCenters,
            $mothersOrderedIds,
            $allowedIds,
            $yearId,
            $ssId,
            (int) $indicator->indicator_type_id,
        );

        $user = $this->authUser();
        $responsibleAccess = app(MetEstablishmentResponsibleAccess::class);

        if ($responsibleAccess->isDesignatedResponsible($user, $yearId, (int) $indicator->indicator_type_id)) {
            $allowedForResponsible = $responsibleAccess->allowedCenterIdsForResponsible(
                $user,
                $yearId,
                (int) $indicator->indicator_type_id,
            );

            $ordered = $ordered->filter(fn (CenCenter $c): bool => $allowedForResponsible->contains($c->id))->values();
        }

        return $forExcel
            ? $ordered->sortBy(fn (CenCenter $c): string => (string) $c->codigo)->values()
            : $ordered;
    }

    private function authUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function currentIndicatorYearModel(): Year
    {
        $year = Session::get('indicator_year');
        abort_unless($year instanceof Year, 500);

        return $year;
    }

    private function assertMetasInstrumentIndicatorType(IndicatorType $indicatorType): void
    {
        if (
            $indicatorType->view !== null
            || $indicatorType->route === null
            || ! str_starts_with((string) $indicatorType->route, 'metas-')
        ) {
            abort(404);
        }
    }

    private function assertIndicatorBelongsToInstrument(MetIndicator $indicator, IndicatorType $indicatorType): void
    {
        if ((int) $indicator->indicator_type_id !== (int) $indicatorType->id) {
            abort(404);
        }
    }

    /**
     * @param  Collection<int, int>  $requestedCenterIds
     * @return Collection<int, CenCenter>
     */
    private function resolveCentersForDataDetailDownload(
        IndicatorType $indicatorType,
        MetIndicator $indicator,
        Collection $requestedCenterIds,
        int $yearId,
    ): Collection {
        $user = $this->authUser();
        $access = app(MetInstrumentDataEntryAccess::class);
        $allowedFromDataEntry = $this->getCentrosParaCargaIndicador($indicator, $yearId, forExcel: true)
            ->keyBy('id');

        $centros = collect();

        foreach ($requestedCenterIds as $centerId) {
            $center = CenCenter::query()->find($centerId);

            if ($center === null) {
                abort(403);
            }

            if ($allowedFromDataEntry->has($center->id)) {
                $centros->push($center);

                continue;
            }

            if ($access->canDownloadComplexFormulaDataDetailForEstablishmentResultsView(
                $user,
                $indicatorType,
                $indicator,
                $center,
                $yearId,
            )) {
                $centros->push($center);

                continue;
            }

            abort(403);
        }

        return $centros->values();
    }

    private function authorizeEstablishmentDataEntry(
        MetIndicator $indicator,
        CenCenter $center,
        IndicatorType $indicatorType,
    ): void {
        $this->authorizeDataEntryForIndicator($indicator);
        $this->authorizeReferenteFieldSsConfigForDataEntry($indicator);
        $this->authorizeEstablishmentCenterScope($indicator, $center, $indicatorType, $this->currentIndicatorYearModel()->id);
    }

    private function authorizeEstablishmentCenterScope(
        MetIndicator $indicator,
        CenCenter $center,
        IndicatorType $indicatorType,
        int $yearId,
    ): void {
        $user = $this->authUser();

        if ($user->hasRole(['control-gestion', 'admin'])) {
            return;
        }

        $responsibleAccess = app(MetEstablishmentResponsibleAccess::class);

        if ($user->hasRole('referente') && $responsibleAccess->isDesignatedResponsible($user, $yearId, (int) $indicatorType->id)) {
            if (! $responsibleAccess->isResponsibleForCenter($user, (int) $center->id, $yearId, (int) $indicatorType->id)) {
                abort(403);
            }
        }

        $loadability = app(MetEstablishmentIndicatorLoadability::class);
        $hieId = (int) $user->cen_hie_dependency_id;

        if ($indicatorType->usesInternalUnitMeasurement()) {
            $isIncluded = MetCenterInclusion::query()
                ->where('cen_center_id', $center->id)
                ->where('year_id', $yearId)
                ->where('indicator_type_id', $indicatorType->id)
                ->exists();

            if (! $isIncluded) {
                abort(403);
            }

            return;
        }

        if (! $loadability->centerIsInMeasurementOrAdosadoOfIncludedMother(
            $center,
            $yearId,
            (int) $indicatorType->id,
            $hieId,
        )) {
            abort(403);
        }

        if (! $loadability->establishmentIndicatorAplicaForCenter(
            (int) $indicator->id,
            $center,
            $yearId,
            $hieId,
            (int) $indicatorType->id,
        )) {
            abort(403);
        }
    }

    /**
     * @param  Collection<int, CenCenter>  $centros
     */
    private function exportIndicatorDataDetailXlsx(
        IndicatorType $indicatorType,
        MetIndicator $indicator,
        Collection $centros,
        string $nombreArchivo,
    ): BinaryFileResponse {
        $yearId = $this->currentIndicatorYearModel()->id;
        $ssId = (int) $this->authUser()->cen_hie_dependency_id;
        $indicator->load('dataFields');

        $indicadores = collect([$indicator]);
        $indicadorIds = $indicadores->pluck('id');
        $centerIds = $centros->pluck('id');

        $requiresUnit = $indicatorType->usesInternalUnitMeasurement();

        $unitsByCenterId = [];

        if ($requiresUnit) {
            $unitsByCenterId = MetMeasurementUnit::query()
                ->whereIn('cen_center_id', $centerIds)
                ->where('year_id', $yearId)
                ->where('cen_hie_dependency_id', $ssId)
                ->where('activo', true)
                ->orderBy('codigo')
                ->get()
                ->groupBy('cen_center_id');
        }

        /** @var array<string, array<string, mixed>> $dataLookup */
        $dataLookupQuery = MetMonthlyData::query()
            ->whereIn('cen_center_id', $centerIds)
            ->where('cen_hie_dependency_id', $ssId)
            ->whereHas('dataField', function ($q) use ($indicadorIds): void {
                $q->whereIn('met_indicator_id', $indicadorIds);
            })
            ->where('year_id', $yearId);

        if (! $requiresUnit) {
            $dataLookupQuery->whereNull('met_measurement_unit_id');
        }

        $dataLookup = $dataLookupQuery
            ->get()
            ->keyBy(function ($row) use ($requiresUnit) {
                $unitPart = $requiresUnit ? (string) ($row->met_measurement_unit_id ?? '') : '0';

                return "{$row->cen_center_id}:{$unitPart}:{$row->met_data_field_id}:".($row->mes ?? '');
            })
            ->toArray();

        $fieldSsConfigService = app(MetFieldSsConfigService::class);
        $effectiveFields = $fieldSsConfigService->applyEffectiveTypes($indicator->dataFields, $ssId, $yearId);
        $periodicFields = $effectiveFields->filter(
            fn (MetDataField $field): bool => $field->input_type->isPeriodic()
        );
        $baselineFields = $effectiveFields->filter(
            fn (MetDataField $field): bool => $field->input_type === DataFieldInputType::LineaBase
        );

        $rutaTemporal = storage_path('temp/'.$nombreArchivo);

        $writer = SimpleExcelWriter::create($rutaTemporal);

        $unitHeaders = $requiresUnit ? ['unidad_codigo'] : [];

        $monthColumns = $this->monthColumnsForPeriodicFields($periodicFields);
        $periodicHeaders = array_merge(
            ['centro_codigo', 'nombre_establecimiento', ...$unitHeaders, 'indicador_clave', 'variable_key', 'componente'],
            array_keys($monthColumns),
        );
        $baselineHeaders = array_merge(
            ['centro_codigo', 'nombre_establecimiento', ...$unitHeaders, 'indicador_clave', 'variable_key', 'componente', 'valor']
        );

        $writer->nameCurrentSheet('Datos_Mensuales');
        $writer->addHeader($periodicHeaders);

        foreach ($centros as $centro) {
            $unitRows = $requiresUnit
                ? ($unitsByCenterId[$centro->id] ?? collect())
                : collect([null]);

            foreach ($unitRows as $unitRow) {
                foreach ($indicadores as $ind) {
                    foreach ($periodicFields as $field) {
                        $rowValues = [
                            'centro_codigo' => $centro->codigo,
                            'nombre_establecimiento' => $centro->nombre_corto,
                            'indicador_clave' => $ind->clave,
                            'variable_key' => $field->variable_key,
                            'componente' => $field->componente->value,
                        ];

                        if ($requiresUnit && $unitRow !== null) {
                            $rowValues['unidad_codigo'] = $unitRow->codigo;
                        }

                        foreach ($this->monthColumnsForField($field) as $mesLabel => $mesNum) {
                            $uid = $requiresUnit && $unitRow !== null ? (string) $unitRow->id : '0';
                            $key = "{$centro->id}:{$uid}:{$field->id}:{$mesNum}";
                            $rowValues[$mesLabel] = isset($dataLookup[$key])
                                ? MetExcelDecimal::formatForExport($dataLookup[$key]['valor'])
                                : null;
                        }
                        $writer->addRow($rowValues);
                    }
                }
            }
        }

        if ($baselineFields->isNotEmpty()) {
            $writer->addNewSheetAndMakeItCurrent('Linea_Base');
            $writer->addHeader($baselineHeaders);

            foreach ($centros as $centro) {
                $unitRows = $requiresUnit
                    ? ($unitsByCenterId[$centro->id] ?? collect())
                    : collect([null]);

                foreach ($unitRows as $unitRow) {
                    foreach ($indicadores as $ind) {
                        foreach ($baselineFields as $field) {
                            $uid = $requiresUnit && $unitRow !== null ? (string) $unitRow->id : '0';
                            $key = "{$centro->id}:{$uid}:{$field->id}:";
                            $row = [
                                'centro_codigo' => $centro->codigo,
                                'nombre_establecimiento' => $centro->nombre_corto,
                                'indicador_clave' => $ind->clave,
                                'variable_key' => $field->variable_key,
                                'componente' => $field->componente->value,
                                'valor' => isset($dataLookup[$key])
                                    ? MetExcelDecimal::formatForExport($dataLookup[$key]['valor'])
                                    : null,
                            ];
                            if ($requiresUnit && $unitRow !== null) {
                                $row['unidad_codigo'] = $unitRow->codigo;
                            }
                            $writer->addRow($row);
                        }
                    }
                }
            }
        }

        return response()->download($rutaTemporal)->deleteFileAfterSend(true);
    }

    private function authorizeDataEntryForIndicator(MetIndicator $indicator): void
    {
        $user = $this->authUser();

        if (! $user->hasRole(['referente', 'control-gestion', 'admin'])) {
            abort(403);
        }

        $yearId = $this->currentIndicatorYearModel()->id;

        if ($indicator->year_id !== $yearId || ! $indicator->estado) {
            abort(404);
        }

        if ($user->hasRole('referente')) {
            $allowed = MetIndicator::query()
                ->where('id', $indicator->id)
                ->whereHas('referentes', function ($q) use ($user, $yearId): void {
                    $q->where('user_id', $user->id)
                        ->where('year_id', $yearId)
                        ->where('cen_hie_dependency_id', $user->cen_hie_dependency_id);
                })
                ->exists();

            if (! $allowed) {
                abort(403);
            }
        }
    }

    private function authorizeReferenteFieldSsConfigForDataEntry(MetIndicator $indicator): void
    {
        $user = $this->authUser();

        if ($user->hasRole(['control-gestion', 'admin'])) {
            return;
        }

        if (! $user->hasRole('referente')) {
            return;
        }

        $indicator->loadMissing('dataFields');

        if (! app(MetFieldSsConfigService::class)->allowsReferenteDataEntry(
            $indicator,
            (int) $user->cen_hie_dependency_id,
            $this->currentIndicatorYearModel()->id,
        )) {
            abort(403, 'Control de Gestión debe definir el tipo de entrada y el tipo de agregación para este indicador antes de cargar datos.');
        }
    }

    private function shouldIgnoreImportedMonthValue(mixed $rawValor): bool
    {
        $parsed = MetExcelDecimal::tryParseImported($rawValor);

        if (! $parsed['valid'] || $parsed['value'] === null) {
            return true;
        }

        return $parsed['value'] === 0.0;
    }

    /**
     * @param  Collection<int, MetDataField>  $periodicFields
     * @return array<string, int>
     */
    private function monthColumnsForPeriodicFields(Collection $periodicFields): array
    {
        $monthNumbers = [];

        foreach ($periodicFields as $field) {
            foreach ($field->input_type->monthNumbers() as $mesNum) {
                $monthNumbers[$mesNum] = true;
            }
        }

        $columns = [];

        foreach (self::MESES as $label => $mesNum) {
            if (isset($monthNumbers[$mesNum])) {
                $columns[$label] = $mesNum;
            }
        }

        return $columns;
    }

    /**
     * @return array<string, int>
     */
    private function monthColumnsForField(MetDataField $field): array
    {
        $allowedMonths = $field->input_type->monthNumbers();
        $columns = [];

        foreach (self::MESES as $label => $mesNum) {
            if (in_array($mesNum, $allowedMonths, true)) {
                $columns[$label] = $mesNum;
            }
        }

        return $columns;
    }
}
