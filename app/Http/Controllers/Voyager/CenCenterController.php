<?php

namespace App\Http\Controllers\Voyager;

use App\Enums\Cen\CenCatalogGlosaRuleKind;
use App\Enums\Cen\CenCenterAdmDependency;
use App\Enums\Cen\CenCenterCertification;
use App\Enums\Cen\CenCenterEmergencyType;
use App\Enums\Cen\CenCenterOrigen;
use App\Enums\Cen\CenCenterProviderType;
use App\Enums\Cen\CenCenterScope;
use App\Enums\Cen\CenCenterSyncRunChangeAction;
use App\Enums\Cen\CenCenterType;
use App\Http\Requests\PromoteCenCatalogGlosaEquivalenceRequest;
use App\Http\Requests\StoreCenCatalogGlosaEquivalenceRequest;
use App\Jobs\SyncNationalEstablishmentsJob;
use App\Models\CenAddress;
use App\Models\CenCatalogGlosaEquivalence;
use App\Models\CenCenter;
use App\Models\CenCenterSyncRun;
use App\Models\CenCenterSyncRunChange;
use App\Models\CenCenterSyncRunIssue;
use App\Models\CenCommune;
use App\Models\CenHieDependency;
use App\Models\CenProvince;
use App\Models\CenRegion;
use App\Models\User;
use App\Services\Cen\CenCatalogGlosaEquivalenceExportService;
use App\Services\Cen\CenCatalogGlosaEquivalenceService;
use App\Services\Cen\CenCenterBitacoraService;
use App\Services\Cen\CenCenterBrowseService;
use App\Services\Cen\CenCenterExcelExportService;
use App\Services\Cen\CenCenterSyncService;
use App\Support\Cen\CenCatalogGlosaResolver;
use App\Support\Voyager\CentinelaValidatesBreadUploads;
use App\Support\Voyager\VoyagerBackedEnumBreadForm;
use BackedEnum;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use TCG\Voyager\Events\BreadDataAdded;
use TCG\Voyager\Events\BreadDataUpdated;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Http\Controllers\VoyagerBaseController;

class CenCenterController extends VoyagerBaseController
{
    use CentinelaValidatesBreadUploads;

    /** @var list<string> */
    private const READ_TABS = [
        'basicos',
        'dependencia',
        'ubicacion',
        'operacionales',
        'bitacora',
        'historial',
    ];

    /** @var list<string> */
    private const BROWSE_TABS = [
        'establecimientos',
        'historial',
    ];

    /**
     * @param  class-string<BackedEnum>  $enumClass
     * @return Collection<int, object{id: string, nombre: string}>
     */
    private function enumCatalogOptions(string $enumClass): Collection
    {
        return collect($enumClass::cases())
            ->map(fn (BackedEnum $case): object => (object) [
                'id' => $case->value,
                'nombre' => $case->value,
            ])
            ->sortBy('nombre')
            ->values();
    }

    private function authenticatedUser(): User
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }

    public function index(Request $request)
    {
        $slug = $this->getSlug($request);

        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();

        $this->authorize('browse', app($dataType->model_name));

        $authUser = $this->authenticatedUser();
        $browseService = app(CenCenterBrowseService::class);
        $browseService->applyRequestedSs($authUser, $request->query('ss'));
        $exploringForeignSs = $browseService->isExploringForeignSs($authUser);

        $nationalSyncEnabled = (bool) config('centinela.national_establishments.enabled', false);
        $canSyncServicioSalud = ! $exploringForeignSs
            && $authUser->hasRole(['admin', 'control-gestion'])
            && $nationalSyncEnabled;
        $canSyncNationalFull = $authUser->hasRole('admin') && $nationalSyncEnabled;
        $canSyncNational = $canSyncServicioSalud;
        $canViewSyncHistory = ($authUser->hasRole(['admin', 'control-gestion']) && $nationalSyncEnabled)
            && Schema::hasTable('cen_center_sync_runs');
        $activeTab = $this->resolveBrowseActiveTab($request, $canViewSyncHistory);

        $browseContext = $browseService->buildForUser(
            $authUser,
            is_string($dataType->scope ?? null) ? $dataType->scope : null,
        );

        $canManageGlosaEquivalences = $authUser->hasRole('admin')
            && Schema::hasTable('cen_catalog_glosa_equivalences');

        $selectedSyncRun = null;
        $syncRunIssues = null;
        $syncRunChanges = null;
        $syncRunPanel = 'changes';
        $activeChangeAction = CenCenterSyncRunChangeAction::Created;
        $syncRuns = null;

        if ($canViewSyncHistory && $activeTab === 'historial') {
            $syncRunId = filter_var($request->query('sync_run'), FILTER_VALIDATE_INT);

            if (is_int($syncRunId) && $syncRunId > 0) {
                $selectedSyncRun = $this->findSyncRunForBrowse($authUser, $syncRunId);

                if ($selectedSyncRun !== null) {
                    $syncRunPanel = $this->resolveSyncRunPanel($request);

                    if ($syncRunPanel === 'issues') {
                        $syncRunIssues = $this->paginateSyncRunIssuesForBrowse($request, $selectedSyncRun);
                    } elseif (Schema::hasTable('cen_center_sync_run_changes')) {
                        $activeChangeAction = $this->resolveSyncRunChangeAction($request, $selectedSyncRun);
                        $syncRunChanges = $this->paginateSyncRunChangesForBrowse(
                            $request,
                            $selectedSyncRun,
                            $activeChangeAction,
                        );
                    }
                }
            }

            if ($selectedSyncRun === null) {
                $syncRuns = $this->paginateSyncRunsForBrowse($request, $authUser);
            }
        }

        $glosaEquivalences = null;
        $unmappedEnumIssues = null;
        $catalogFieldLabels = CenCatalogGlosaResolver::FIELD_LABELS;
        $catalogEnumOptions = [];

        foreach (CenCatalogGlosaResolver::fieldKeys() as $fieldKey) {
            $catalogEnumOptions[$fieldKey] = CenCatalogGlosaResolver::enumOptionsForField($fieldKey);
        }

        if ($canManageGlosaEquivalences && $activeTab === 'historial') {
            $equivalenceService = app(CenCatalogGlosaEquivalenceService::class);
            $glosaEquivalences = $equivalenceService->paginateActive(15)->appends(
                $request->except('equiv_page')
            );
            $unmappedEnumIssues = $equivalenceService->paginatePromotableUnmappedIssues(15)->appends(
                $request->except('unmapped_page')
            );
        }

        $syncInProgress = false;
        $syncFullInProgress = false;
        $syncService = app(CenCenterSyncService::class);

        if ($canSyncServicioSalud) {
            $ssCodigoBrowse = $this->resolveSsCodigoForNationalSync($authUser);
            if ($ssCodigoBrowse !== null) {
                $syncInProgress = $syncService->hasActiveSyncRun($ssCodigoBrowse);
            }
        }

        if ($canSyncNationalFull) {
            $syncFullInProgress = $syncService->hasActiveSyncRun(null);
        }

        $showBrowseToolbar = $this->shouldShowBrowseToolbar(
            $authUser,
            $dataType,
            $canSyncServicioSalud || $canSyncNationalFull,
        );

        $view = view()->exists("voyager::$slug.browse")
            ? "voyager::$slug.browse"
            : 'voyager::bread.browse';

        return Voyager::view($view, compact(
            'activeTab',
            'browseContext',
            'canManageGlosaEquivalences',
            'canSyncNational',
            'canSyncNationalFull',
            'canSyncServicioSalud',
            'canViewSyncHistory',
            'catalogEnumOptions',
            'catalogFieldLabels',
            'dataType',
            'exploringForeignSs',
            'glosaEquivalences',
            'activeChangeAction',
            'selectedSyncRun',
            'showBrowseToolbar',
            'syncRunChanges',
            'syncRunIssues',
            'syncRunPanel',
            'syncRuns',
            'syncFullInProgress',
            'syncInProgress',
            'unmappedEnumIssues',
        ));
    }

    private function shouldShowBrowseToolbar(User $user, Model $dataType, bool $canSyncNational): bool
    {
        if ($canSyncNational) {
            return true;
        }

        $model = app($dataType->model_name);

        if ($user->can('add', $model)) {
            return true;
        }

        return $user->can('edit', $model)
            && ! empty($dataType->order_column)
            && ! empty($dataType->order_display_column);
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        $slug = $this->getSlug($request);

        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();

        $this->authorize('browse', app($dataType->model_name));

        $authUser = $this->authenticatedUser();

        return app(CenCenterExcelExportService::class)->downloadForUser(
            $authUser,
            is_string($dataType->scope ?? null) ? $dataType->scope : null,
        );
    }

    public function create(Request $request)
    {
        $slug = $this->getSlug($request);

        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();

        $this->authorize('add', app($dataType->model_name));

        $dataTypeContent = (strlen($dataType->model_name) != 0)
            ? new $dataType->model_name
            : false;

        foreach ($dataType->addRows as $key => $row) {
            $dataType->addRows[$key]['col_width'] = $row->details->width ?? 100;
        }

        $this->removeRelationshipField($dataType, 'add');

        $isModelTranslatable = is_bread_translatable($dataTypeContent);

        $this->eagerLoadRelations($dataTypeContent, $dataType, 'add', $isModelTranslatable);

        $regions = CenRegion::orderBy('nombre')->get();
        $hieDependencies = CenHieDependency::orderBy('nombre')->get();
        $types = $this->enumCatalogOptions(CenCenterType::class);
        $scopes = $this->enumCatalogOptions(CenCenterScope::class);
        $certifications = $this->enumCatalogOptions(CenCenterCertification::class);
        $emergencyTypes = $this->enumCatalogOptions(CenCenterEmergencyType::class);
        $providerTypes = $this->enumCatalogOptions(CenCenterProviderType::class);
        $admDependencies = $this->enumCatalogOptions(CenCenterAdmDependency::class);

        $view = 'voyager::bread.edit-add';

        if (view()->exists("voyager::$slug.edit-add")) {
            $view = "voyager::$slug.edit-add";
        }

        return Voyager::view($view, compact(
            'dataType',
            'dataTypeContent',
            'isModelTranslatable',
            'regions',
            'hieDependencies',
            'types',
            'scopes',
            'certifications',
            'emergencyTypes',
            'providerTypes',
            'admDependencies'
        ));
    }

    public function store(Request $request)
    {
        $slug = $this->getSlug($request);

        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();

        $this->authorize('add', app($dataType->model_name));

        $user = $this->authenticatedUser();

        $val = $this->validateBread($request->all(), $dataType->addRows)->validate();

        return DB::transaction(function () use ($request, $slug, $dataType, $user) {
            $model = new $dataType->model_name;
            $model->origen = CenCenterOrigen::Manual;
            $data = $this->insertUpdateData($request, $slug, $dataType->addRows, $model);

            $this->saveAddress($request, $data->id);

            event(new BreadDataAdded($dataType, $data));

            if (! $request->has('_tagging')) {
                if ($user->can('browse', $data)) {
                    $redirect = redirect()->route("voyager.{$dataType->slug}.index");
                } else {
                    $redirect = redirect()->route("voyager.{$dataType->slug}.show", $data->id);
                }

                return $redirect->with([
                    'message' => __('voyager::generic.successfully_added_new')." {$dataType->getTranslatedAttribute('display_name_singular')}",
                    'alert-type' => 'success',
                ]);
            }

            return response()->json(['success' => true, 'data' => $data]);
        });
    }

    public function show(Request $request, mixed $id)
    {
        $slug = $this->getSlug($request);

        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();

        $isSoftDeleted = false;

        if (strlen($dataType->model_name) != 0) {
            $model = app($dataType->model_name);
            $query = $model->query()
                ->with([
                    'cenAddress.cenCommune.cenProvince.cenRegion',
                    'cenHieDependency',
                ]);

            if ($model && in_array(SoftDeletes::class, class_uses_recursive($model))) {
                $query = $query->withTrashed();
            }
            $dataTypeContent = call_user_func([$query, 'findOrFail'], $id);
            if ($dataTypeContent->deleted_at) {
                $isSoftDeleted = true;
            }
        } else {
            $dataTypeContent = DB::table($dataType->name)->where('id', $id)->first();
        }

        $dataTypeContent = $this->resolveRelations($dataTypeContent, $dataType);

        $this->removeRelationshipField($dataType, 'read');

        $this->authorize('read', $dataTypeContent);

        $isModelTranslatable = is_bread_translatable($dataTypeContent);

        $this->eagerLoadRelations($dataTypeContent, $dataType, 'read', $isModelTranslatable);

        $view = 'voyager::bread.read';

        if (view()->exists("voyager::$slug.read")) {
            $view = "voyager::$slug.read";
        }

        $authUser = $this->authenticatedUser();
        $canViewBitacora = $this->canManageEstablishmentBitacora($authUser);
        $canViewHistorial = $authUser->hasRole(['admin', 'control-gestion'])
            && Schema::hasTable('cen_center_sync_run_issues');
        $activeReadTab = $this->resolveReadTab($request, $canViewBitacora, $canViewHistorial);

        $notes = collect();
        $centerSyncIssues = null;
        $centerAudits = null;

        if ($dataTypeContent instanceof CenCenter) {
            if ($canViewBitacora) {
                $notes = $dataTypeContent->notes()->with('user')->latest()->get();
            }

            if ($canViewHistorial) {
                $centerSyncIssues = $this->paginateCenterSyncIssues($request, $dataTypeContent, $authUser);
            }

            if (config('audit.enabled') && Schema::hasTable('audits')) {
                $centerAudits = $dataTypeContent->audits()
                    ->with('user')
                    ->latest()
                    ->paginate(25, ['*'], 'audit_page')
                    ->appends($request->except('audit_page'));
            }
        }

        $showUrl = Route::has('voyager.establecimientos.show')
            ? route('voyager.establecimientos.show', $id)
            : url("/admin/establecimientos/{$id}");

        return Voyager::view($view, compact(
            'dataType',
            'dataTypeContent',
            'isModelTranslatable',
            'isSoftDeleted',
            'notes',
            'authUser',
            'activeReadTab',
            'canViewBitacora',
            'canViewHistorial',
            'centerSyncIssues',
            'centerAudits',
            'showUrl',
        ));
    }

    public function edit(Request $request, mixed $id)
    {
        $slug = $this->getSlug($request);

        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();

        if (strlen($dataType->model_name) != 0) {
            $model = app($dataType->model_name);
            $query = $model->query();

            if ($model && in_array(SoftDeletes::class, class_uses_recursive($model))) {
                $query = $query->withTrashed();
            }
            $dataTypeContent = call_user_func([$query, 'findOrFail'], $id);
        } else {
            $dataTypeContent = DB::table($dataType->name)->where('id', $id)->first();
        }

        foreach ($dataType->editRows as $key => $row) {
            $dataType->editRows[$key]['col_width'] = isset($row->details->width) ? $row->details->width : 100;
        }

        $this->removeRelationshipField($dataType, 'edit');

        $this->authorize('edit', $dataTypeContent);

        $isModelTranslatable = is_bread_translatable($dataTypeContent);

        $this->eagerLoadRelations($dataTypeContent, $dataType, 'edit', $isModelTranslatable);

        if ($dataTypeContent instanceof CenCenter) {
            VoyagerBackedEnumBreadForm::exposeStringValues(
                $dataTypeContent,
                VoyagerBackedEnumBreadForm::CEN_CENTER_BREAD_ENUM_FIELDS
            );
        }

        $regions = CenRegion::orderBy('nombre')->get();
        $hieDependencies = CenHieDependency::orderBy('nombre')->get();
        $types = $this->enumCatalogOptions(CenCenterType::class);
        $scopes = $this->enumCatalogOptions(CenCenterScope::class);
        $certifications = $this->enumCatalogOptions(CenCenterCertification::class);
        $emergencyTypes = $this->enumCatalogOptions(CenCenterEmergencyType::class);
        $providerTypes = $this->enumCatalogOptions(CenCenterProviderType::class);
        $admDependencies = $this->enumCatalogOptions(CenCenterAdmDependency::class);

        $address = $dataTypeContent->cenAddress;

        $selectedRegionId = null;
        $selectedProvinceId = null;
        $selectedCommuneId = null;

        if ($address && $address->cenCommune) {
            $selectedCommuneId = $address->cen_commune_id;
            $selectedProvinceId = $address->cenCommune->cen_province_id;
            if ($address->cenCommune->cenProvince) {
                $selectedRegionId = $address->cenCommune->cenProvince->cen_region_id;
            }
        }

        $provinces = $selectedRegionId
            ? CenProvince::where('cen_region_id', $selectedRegionId)->orderBy('nombre')->get()
            : collect();

        $communes = $selectedProvinceId
            ? CenCommune::where('cen_province_id', $selectedProvinceId)->orderBy('nombre')->get()
            : collect();

        $view = 'voyager::bread.edit-add';

        if (view()->exists("voyager::$slug.edit-add")) {
            $view = "voyager::$slug.edit-add";
        }

        return Voyager::view($view, compact(
            'dataType',
            'dataTypeContent',
            'isModelTranslatable',
            'regions',
            'hieDependencies',
            'types',
            'scopes',
            'certifications',
            'emergencyTypes',
            'providerTypes',
            'admDependencies',
            'address',
            'selectedRegionId',
            'selectedProvinceId',
            'selectedCommuneId',
            'provinces',
            'communes'
        ));
    }

    public function update(Request $request, mixed $id)
    {
        $slug = $this->getSlug($request);

        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();

        $id = $id instanceof Model ? $id->{$id->getKeyName()} : $id;

        $model = app($dataType->model_name);
        $query = $model->query();
        if ($model && in_array(SoftDeletes::class, class_uses_recursive($model))) {
            $query = $query->withTrashed();
        }

        $data = $query->findOrFail($id);

        $this->authorize('edit', $data);

        if ($data instanceof CenCenter && $data->origen === CenCenterOrigen::Api) {
            abort(403, 'Los datos oficiales de establecimientos API no se editan manualmente. Use sincronización o bitácora.');
        }

        $val = $this->validateBread($request->all(), $dataType->editRows, $dataType->name, $id)->validate();

        $to_remove = $dataType->editRows->where('type', 'image')
            ->filter(function ($item, $key) use ($request) {
                return $request->hasFile($item->field);
            });
        $original_data = clone $data;
        $user = $this->authenticatedUser();

        return DB::transaction(function () use ($request, $slug, $dataType, $data, $to_remove, $original_data, $id, $user) {
            $this->insertUpdateData($request, $slug, $dataType->editRows, $data);

            $this->deleteBreadImages($original_data, $to_remove);

            $this->saveAddress($request, $id);

            event(new BreadDataUpdated($dataType, $data));

            if ($user->can('browse', app($dataType->model_name))) {
                $redirect = redirect()->route("voyager.{$dataType->slug}.index");
            } else {
                $redirect = redirect()->route("voyager.{$dataType->slug}.show", $request->id);
            }

            return $redirect->with([
                'message' => __('voyager::generic.successfully_updated')." {$dataType->getTranslatedAttribute('display_name_singular')}",
                'alert-type' => 'success',
            ]);
        });
    }

    public function syncNational(Request $request): RedirectResponse
    {
        $user = $this->authenticatedUser();

        if (! $user->hasRole(['admin', 'control-gestion'])) {
            abort(403);
        }

        if (! config('centinela.national_establishments.enabled', false)) {
            return redirect()->back()->with([
                'message' => 'Sincronización deshabilitada en settings (module.national_establishments_enabled).',
                'alert-type' => 'warning',
            ]);
        }

        $ssCodigo = $this->resolveSsCodigoForNationalSync($user);

        if ($ssCodigo === null) {
            return redirect()->back()->with([
                'message' => 'Su usuario no tiene un Servicio de Salud asociado; no se puede encolar la sincronización.',
                'alert-type' => 'warning',
            ]);
        }

        try {
            app(CenCenterSyncService::class)->ensureSyncNotAlreadyRunning($ssCodigo);
        } catch (\RuntimeException $exception) {
            return redirect()->back()->with([
                'message' => $exception->getMessage(),
                'alert-type' => 'warning',
            ]);
        }

        SyncNationalEstablishmentsJob::dispatch($ssCodigo, $user->id);

        return $this->redirectToEstablecimientosBrowse()->with([
            'message' => "Sincronización del catálogo encolada para el SS {$ssCodigo}. Se procesará en segundo plano (requiere worker de colas activo).",
            'alert-type' => 'success',
        ]);
    }

    public function syncNationalFull(Request $request): RedirectResponse
    {
        $user = $this->authenticatedUser();

        if (! $user->hasRole('admin')) {
            abort(403);
        }

        if (! config('centinela.national_establishments.enabled', false)) {
            return redirect()->back()->with([
                'message' => 'Sincronización deshabilitada en settings (module.national_establishments_enabled).',
                'alert-type' => 'warning',
            ]);
        }

        try {
            app(CenCenterSyncService::class)->ensureSyncNotAlreadyRunning(null);
        } catch (\RuntimeException $exception) {
            return redirect()->back()->with([
                'message' => $exception->getMessage(),
                'alert-type' => 'warning',
            ]);
        }

        SyncNationalEstablishmentsJob::dispatch(null, $user->id);

        return $this->redirectToEstablecimientosBrowse()->with([
            'message' => 'Sincronización nacional completa encolada (mismo job que cen:sync-establishments semanal). Se procesará en segundo plano (requiere worker de colas activo).',
            'alert-type' => 'success',
        ]);
    }

    private function resolveSsCodigoForNationalSync(User $user): ?string
    {
        if ($user->cen_hie_dependency_id === null) {
            return null;
        }

        $codigo = CenHieDependency::query()
            ->whereKey($user->cen_hie_dependency_id)
            ->value('codigo');

        if (! is_string($codigo) || $codigo === '') {
            return null;
        }

        return $codigo;
    }

    private function redirectToEstablecimientosBrowse(): RedirectResponse
    {
        if (Route::has('voyager.establecimientos.index')) {
            return redirect()->route('voyager.establecimientos.index');
        }

        return redirect('/admin/establecimientos');
    }

    private function redirectToEstablecimientoShow(int $id, ?string $tab = null): RedirectResponse
    {
        $parameters = ['id' => $id];

        if ($tab !== null && $tab !== '') {
            $parameters['tab'] = $tab;
        }

        if (Route::has('voyager.establecimientos.show')) {
            return redirect()->route('voyager.establecimientos.show', $parameters);
        }

        $url = '/admin/establecimientos/'.$id;

        if ($tab !== null && $tab !== '') {
            $url .= '?tab='.$tab;
        }

        return redirect($url);
    }

    private function canManageEstablishmentBitacora(User $user): bool
    {
        return $user->hasRole('admin');
    }

    private function resolveBrowseActiveTab(Request $request, bool $canViewSyncHistory): string
    {
        $tab = $request->query('tab', 'establecimientos');

        if ($tab === 'servicio-salud') {
            $tab = 'establecimientos';
        }

        if (! is_string($tab) || ! in_array($tab, self::BROWSE_TABS, true)) {
            return 'establecimientos';
        }

        if ($tab === 'historial' && ! $canViewSyncHistory) {
            return 'establecimientos';
        }

        return $tab;
    }

    private function resolveReadTab(Request $request, bool $canViewBitacora, bool $canViewHistorial): string
    {
        $tab = $request->query('tab', 'basicos');

        if (! is_string($tab) || ! in_array($tab, self::READ_TABS, true)) {
            return 'basicos';
        }

        if ($tab === 'bitacora' && ! $canViewBitacora) {
            return 'basicos';
        }

        if ($tab === 'historial' && ! $canViewHistorial) {
            return 'basicos';
        }

        return $tab;
    }

    private function paginateCenterSyncIssues(Request $request, CenCenter $center, User $user): LengthAwarePaginator
    {
        $query = CenCenterSyncRunIssue::query()
            ->with(['syncRun.triggeredBy'])
            ->where('establecimiento_codigo', $center->codigo)
            ->orderByDesc('id');

        if ($user->hasRole('control-gestion') && ! $user->hasRole('admin')) {
            $ssCodigo = CenHieDependency::query()
                ->whereKey($user->cen_hie_dependency_id)
                ->value('codigo');

            if (is_string($ssCodigo) && $ssCodigo !== '') {
                $query->whereHas('syncRun', fn ($q) => $q->where('ss_codigo', $ssCodigo));
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query->paginate(25, ['*'], 'sync_issues_page')->appends(
            $request->except('sync_issues_page')
        );
    }

    private function findSyncRunForBrowse(User $user, int $runId): ?CenCenterSyncRun
    {
        $withCount = [
            'issues',
            'issues as pending_unmapped_issues_count' => static function ($issueQuery): void {
                $issueQuery->where('code', 'unmapped_enum')->whereNull('promoted_at');
            },
        ];

        return $this->syncRunsQueryForBrowse($user)
            ->with(['triggeredBy'])
            ->withCount($withCount)
            ->whereKey($runId)
            ->first();
    }

    private function resolveSyncRunPanel(Request $request): string
    {
        $panel = $request->query('run_panel', 'changes');

        return is_string($panel) && $panel === 'issues' ? 'issues' : 'changes';
    }

    private function resolveSyncRunChangeAction(Request $request, CenCenterSyncRun $run): CenCenterSyncRunChangeAction
    {
        $action = $request->query('change_action');

        if (is_string($action)) {
            $resolved = CenCenterSyncRunChangeAction::tryFrom($action);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        return CenCenterSyncRunChangeAction::defaultForRun($run);
    }

    private function paginateSyncRunChangesForBrowse(
        Request $request,
        CenCenterSyncRun $run,
        CenCenterSyncRunChangeAction $action,
    ): LengthAwarePaginator {
        return CenCenterSyncRunChange::query()
            ->where('cen_center_sync_run_id', $run->id)
            ->where('action', $action->value)
            ->orderByDesc('id')
            ->paginate(25, ['*'], 'run_changes_page')
            ->appends(array_merge(
                $request->except('run_changes_page'),
                [
                    'tab' => 'historial',
                    'sync_run' => $run->id,
                    'run_panel' => 'changes',
                    'change_action' => $action->value,
                ],
            ));
    }

    private function paginateSyncRunIssuesForBrowse(Request $request, CenCenterSyncRun $run): LengthAwarePaginator
    {
        return CenCenterSyncRunIssue::query()
            ->where('cen_center_sync_run_id', $run->id)
            ->orderByDesc('id')
            ->paginate(25, ['*'], 'run_issues_page')
            ->appends(array_merge(
                $request->except('run_issues_page'),
                ['tab' => 'historial', 'sync_run' => $run->id, 'run_panel' => 'issues'],
            ));
    }

    private function paginateSyncRunsForBrowse(Request $request, User $user): LengthAwarePaginator
    {
        return $this->syncRunsQueryForBrowse($user)
            ->with(['triggeredBy'])
            ->withCount([
                'issues',
                'issues as pending_unmapped_issues_count' => static function ($issueQuery): void {
                    $issueQuery->where('code', 'unmapped_enum')->whereNull('promoted_at');
                },
            ])
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'sync_page')
            ->appends($request->except('sync_page'));
    }

    /**
     * @return Builder<CenCenterSyncRun>
     */
    private function syncRunsQueryForBrowse(User $user): Builder
    {
        $query = CenCenterSyncRun::query();

        if ($user->hasRole('control-gestion') && ! $user->hasRole('admin')) {
            $ssCodigo = CenHieDependency::query()
                ->whereKey($user->cen_hie_dependency_id)
                ->value('codigo');

            if (is_string($ssCodigo) && $ssCodigo !== '') {
                $query->where('ss_codigo', $ssCodigo);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }

    public function storeNote(Request $request, int $id)
    {
        $center = CenCenter::query()->findOrFail($id);
        $this->authorize('read', $center);

        $user = $this->authenticatedUser();

        if (! $this->canManageEstablishmentBitacora($user)) {
            abort(403);
        }

        if (
            app(CenCenterBrowseService::class)->isExploringForeignSs($user)
            && (int) $center->cen_hie_dependency_id !== (int) $user->cen_hie_dependency_id
        ) {
            abort(403);
        }

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'tarea_pendiente' => ['sometimes', 'boolean'],
        ]);

        $note = app(CenCenterBitacoraService::class)->storeNote(
            $center,
            $user,
            $validated['body'] ?? null,
            $request->boolean('tarea_pendiente'),
        );

        $message = match ($note->note_kind) {
            CenCenterBitacoraService::NOTE_KIND_PENDING_MARKED => 'Tarea pendiente registrada en bitácora.',
            CenCenterBitacoraService::NOTE_KIND_PENDING_CLEARED => 'Cierre de tarea pendiente registrado en bitácora.',
            default => 'Nota registrada en bitácora.',
        };

        return $this->redirectToEstablecimientoShow($id, 'bitacora')->with([
            'message' => $message,
            'alert-type' => 'success',
        ]);
    }

    public function storeGlosaEquivalence(
        StoreCenCatalogGlosaEquivalenceRequest $request,
        CenCatalogGlosaEquivalenceService $equivalenceService,
    ): RedirectResponse {
        $dataType = Voyager::model('DataType')->where('slug', '=', 'establecimientos')->firstOrFail();
        $this->authorize('browse', app($dataType->model_name));

        $user = $this->authenticatedUser();

        $ruleKind = CenCatalogGlosaRuleKind::tryFrom((string) $request->input('rule_kind', 'alias'))
            ?? CenCatalogGlosaRuleKind::Alias;

        $equivalenceService->register(
            $request->string('field_key')->toString(),
            $request->string('source_glosa')->toString(),
            $request->string('target_value')->toString(),
            $user,
            $ruleKind,
        );

        return $this->redirectToBrowseHistorial()->with([
            'message' => 'Equivalencia registrada. Se aplicará en la próxima sincronización con el catálogo nacional.',
            'alert-type' => 'success',
        ]);
    }

    public function promoteGlosaEquivalenceFromIssue(
        PromoteCenCatalogGlosaEquivalenceRequest $request,
        CenCenterSyncRunIssue $issue,
        CenCatalogGlosaEquivalenceService $equivalenceService,
    ): RedirectResponse {
        $dataType = Voyager::model('DataType')->where('slug', '=', 'establecimientos')->firstOrFail();
        $this->authorize('browse', app($dataType->model_name));

        $user = $this->authenticatedUser();

        if (! $user->hasRole('admin')) {
            abort(403);
        }

        $equivalenceService->registerFromSyncIssue(
            $issue,
            $request->string('target_value')->toString(),
            $this->authenticatedUser(),
        );

        return $this->redirectToBrowseHistorial()->with([
            'message' => 'Equivalencia creada desde la incidencia. Se aplicará en la próxima sincronización nacional.',
            'alert-type' => 'success',
        ]);
    }

    public function exportGlosaEquivalences(
        CenCatalogGlosaEquivalenceExportService $exportService,
    ): BinaryFileResponse {
        $dataType = Voyager::model('DataType')->where('slug', '=', 'establecimientos')->firstOrFail();
        $this->authorize('browse', app($dataType->model_name));

        if (! $this->authenticatedUser()->hasRole('admin')) {
            abort(403);
        }

        if (! Schema::hasTable('cen_catalog_glosa_equivalences')) {
            abort(404);
        }

        return $exportService->downloadActive();
    }

    public function deactivateGlosaEquivalence(
        CenCatalogGlosaEquivalence $equivalence,
        CenCatalogGlosaEquivalenceService $equivalenceService,
    ): RedirectResponse {
        $dataType = Voyager::model('DataType')->where('slug', '=', 'establecimientos')->firstOrFail();
        $this->authorize('browse', app($dataType->model_name));

        if (! $this->authenticatedUser()->hasRole('admin')) {
            abort(403);
        }

        $equivalenceService->deactivate($equivalence);

        return $this->redirectToBrowseHistorial()->with([
            'message' => 'Equivalencia desactivada.',
            'alert-type' => 'success',
        ]);
    }

    private function redirectToBrowseHistorial(): RedirectResponse
    {
        $url = Route::has('voyager.establecimientos.index')
            ? route('voyager.establecimientos.index', ['tab' => 'historial'])
            : url('/admin/establecimientos?tab=historial');

        return redirect()->to($url);
    }

    public function mergeCodigo(Request $request, int $id, CenCenterSyncService $syncService)
    {
        $center = CenCenter::query()->findOrFail($id);
        $this->authorize('edit', $center);

        $user = $this->authenticatedUser();

        if (! $user->hasRole('admin')) {
            abort(403);
        }

        $validated = $request->validate([
            'national_codigo' => ['required', 'string', 'max:32'],
        ]);

        $syncService->mergeManualToApi($center, $validated['national_codigo'], $user);

        return redirect()->route('voyager.establecimientos.show', $id)->with([
            'message' => 'Establecimiento fusionado con código nacional y sincronizado.',
            'alert-type' => 'success',
        ]);
    }

    protected function saveAddress(Request $request, int $cenCenterId): void
    {
        $via = $request->input('address_via');
        if (! $via) {
            return;
        }

        CenAddress::updateOrCreate(
            ['cen_center_id' => $cenCenterId],
            [
                'cen_commune_id' => $request->input('address_cen_commune_id'),
                'via' => $via,
                'numero' => $request->input('address_numero'),
                'direccion' => $request->input('address_direccion'),
                'telefono' => $request->input('address_telefono'),
                'latitud' => $request->input('address_latitud'),
                'longitud' => $request->input('address_longitud'),
            ]
        );
    }
}
