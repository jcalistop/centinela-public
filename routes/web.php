<?php

use App\Http\Controllers\AccessRequestController;
use App\Http\Controllers\AcercaDeController;
use App\Http\Controllers\AdpIndicatorCatalogController;
use App\Http\Controllers\AdpInstrumentController;
use App\Http\Controllers\AdpSelfEvaluationController;
use App\Http\Controllers\CentinelaLegalDocController;
use App\Http\Controllers\ComgesController;
use App\Http\Controllers\ComgesMassResultsController;
use App\Http\Controllers\CspReportController;
use App\Http\Controllers\ExtendVoyagerAuthController;
use App\Http\Controllers\HelperController;
use App\Http\Controllers\MetasInstrumentController;
use App\Http\Controllers\MetIndicatorCatalogController;
use App\Http\Controllers\PublicDashboardController;
use App\Http\Controllers\StorageController;
use App\Http\Controllers\Support\SupportTicketController;
use App\Http\Controllers\Support\SupportTicketReceiverController;
use App\Http\Controllers\UserManualController;
use App\Http\Controllers\Voyager\CenCenterController;
use App\Http\Controllers\Voyager\CentinelaVoyagerBreadController;
use App\Http\Controllers\Voyager\CentinelaVoyagerDatabaseController;
use App\Http\Controllers\Voyager\CentinelaVoyagerUserController;
use App\Http\Controllers\Voyager\ComCommitmentController;
use App\Http\Controllers\Voyager\ComIndicatorController;
use App\Http\Controllers\Voyager\ComIndicatorCutoffController;
use App\Http\Controllers\Voyager\DocumentController;
use App\Livewire\Configuracion\PublicInfoPanels\PublicInfoPanelEditor;
use App\Livewire\Configuracion\PublicInfoPanels\PublicInfoPanelIndex;
use App\Livewire\Configuracion\PublicInfoPanels\PublicInfoPanelShow;
use App\Livewire\Configuracion\SettingsManager;
use App\Livewire\Dashboard\NonAdminDashboardDetail;
use App\Livewire\Messaging\MessagingCampaignComposer;
use App\Models\CenCommune;
use App\Models\CenProvince;
use App\Models\IndicatorType;
use App\Models\User;
use App\Support\Configuration\FilamentChasisGate;
use App\Support\Configuration\PublicDashboardGate;
use App\Support\Dashboard\PublicInfoPanelAgentGuide;
use App\Support\Filament\FilamentPanelRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Http\Middleware\VoyagerAdminMiddleware;

Route::get('/', [PublicDashboardController::class, 'landing'])->name('centinela.publico.landing');
Route::get('/publico/centinela', [PublicDashboardController::class, 'plataforma'])
    ->name('centinela.publico.plataforma');
Route::get('/publico/contrato-agentes-paneles.md', function () {
    if (! PublicDashboardGate::enabled()) {
        return redirect()->route('voyager.login');
    }

    return PublicInfoPanelAgentGuide::downloadResponse();
})->name('centinela.publico.contrato-agentes');
Route::get('/publico/paneles/{slug}', [PublicDashboardController::class, 'showPanel'])
    ->where('slug', '[A-Za-z0-9\-]+')
    ->name('centinela.publico.panel');
Route::get('/publico/paneles/{slug}/preview', [PublicDashboardController::class, 'previewPanel'])
    ->where('slug', '[A-Za-z0-9\-]+')
    ->middleware('signed')
    ->name('centinela.publico.panel.preview');
Route::get('/publico/ss/{ss}', [PublicDashboardController::class, 'show'])
    ->whereNumber('ss')
    ->name('centinela.publico.ss');
Route::get('/storage/public-info-panels/{slug}/p/{expires}/{signature}/r/{path}', [StorageController::class, 'showPublicInfoPanelRelative'])
    ->where('slug', '[A-Za-z0-9\-]+')
    ->where('expires', '[0-9]+')
    ->where('signature', '[a-f0-9]{64}')
    ->where('path', '.*')
    ->name('centinela.storage.public-panel-relative-signed');
Route::get('/storage/public-info-panels/{slug}/p/{expires}/{signature}/{asset}', [StorageController::class, 'showPublicInfoPanelAsset'])
    ->where('slug', '[A-Za-z0-9\-]+')
    ->where('expires', '[0-9]+')
    ->where('signature', '[a-f0-9]{64}')
    ->where('asset', 'html|csv|preview')
    ->name('centinela.storage.public-panel-signed');
Route::get('/storage/public-info-panels/{slug}/r/{path}', [StorageController::class, 'showPublicInfoPanelRelative'])
    ->where('slug', '[A-Za-z0-9\-]+')
    ->where('path', '.*')
    ->name('centinela.storage.public-panel-relative');
Route::get('/storage/public-info-panels/{slug}/{asset}', [StorageController::class, 'showPublicInfoPanelAsset'])
    ->where('slug', '[A-Za-z0-9\-]+')
    ->where('asset', 'html|csv|preview')
    ->name('centinela.storage.public-panel');

Route::get('/configuracion/public-info-panels/contrato-agentes-paneles.md', function () {
    return PublicInfoPanelAgentGuide::downloadResponse();
})->middleware('auth')->name('filament.public-info-panels.agent-guide');

Route::get('/configuracion/public-info-panels/instrucciones-agentes-paneles.md', function () {
    return redirect('/configuracion/public-info-panels/contrato-agentes-paneles.md');
});

Route::get('/pais/public-info-panels/instrucciones-agentes-paneles.md', function () {
    return redirect('/configuracion/public-info-panels/contrato-agentes-paneles.md');
});

Route::get('/pais/public-info-panels/contrato-agentes-paneles.md', function () {
    return redirect('/configuracion/public-info-panels/contrato-agentes-paneles.md');
});

Route::get('/filament/public-info-panels/instrucciones-agentes-paneles.md', function () {
    abort_unless(FilamentChasisGate::enabled(), 404);

    return redirect('/configuracion/public-info-panels/contrato-agentes-paneles.md');
});

Route::get('/filament/public-info-panels/contrato-agentes-paneles.md', function () {
    abort_unless(FilamentChasisGate::enabled(), 404);

    return redirect('/configuracion/public-info-panels/contrato-agentes-paneles.md');
});

Route::any('/filament/{path?}', function () {
    abort_unless(FilamentChasisGate::enabled(), 404);

    $user = Auth::user();

    if (! $user instanceof User) {
        return redirect('/servicio-salud/login');
    }

    return redirect(FilamentPanelRegistry::homeUrlForUser($user));
})->where('path', '.*')->name('filament.legacy-redirect');

Route::post('/csp-report', CspReportController::class)->name('csp.report');

Route::get('/login', function (Request $request) {
    $target = route('voyager.login');
    $query = $request->getQueryString();
    if ($query !== null && $query !== '') {
        $target .= '?'.$query;
    }

    return redirect()->to($target);
})->name('login');

Route::get('/legal/terminos-de-uso', [CentinelaLegalDocController::class, 'terminos'])
    ->name('centinela.legal.terminos');
Route::get('/legal/politica-de-privacidad', [CentinelaLegalDocController::class, 'privacidad'])
    ->name('centinela.legal.privacidad');

Route::get('/solicitar-acceso', [AccessRequestController::class, 'create'])
    ->name('centinela.access-request.create');
Route::post('/solicitar-acceso', [AccessRequestController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('centinela.access-request.store');

Route::get('/storage/settings/{filepath}', [StorageController::class, 'showSettings'])
    ->where('filepath', '.*')
    ->middleware(['auth', 'readonly.files:settings-images']);
Route::get('/storage/servicios-de-salud/{filepath}', [StorageController::class, 'showServicioSaludLogo'])
    ->where('filepath', '.*')
    ->name('centinela.storage.ss-logo'); // gated in controller (SDD-282)
Route::get('/storage/users/{filepath}', [StorageController::class, 'showAvatar'])
    ->where('filepath', '.*')
    ->middleware(['auth', 'readonly.files:users-default-avatar']);
Route::get('/download/{slug}/{field}/{id}/{file_index}', [StorageController::class, 'downloadFile'])
    ->name('download-file')
    ->middleware(['auth', 'readonly.files:download-document-only']);
Route::get('/view/{slug}/{field}/{id}/{file_index}', [StorageController::class, 'viewFileInline'])
    ->name('view-document-file')
    ->middleware(['auth', 'readonly.files:download-document-only']);

Route::group(['prefix' => 'admin', 'middleware' => ['auth', VoyagerAdminMiddleware::class]], function (): void {
    // Antes de Voyager::routes(): {id} capturaría segmentos literales como show.
    Route::get('compromisos/estructura', [ComCommitmentController::class, 'structureOrder'])
        ->name('voyager.compromisos.structure-order');
    Route::get('establecimientos/export', [CenCenterController::class, 'exportExcel'])
        ->name('voyager.establecimientos.export');
    // SDD-306: curaduría de paneles públicos en Voyager (Livewire full-page; solo rol admin en el componente).
    Route::get('paneles-publicos', PublicInfoPanelIndex::class)
        ->name('centinela.admin.paneles-publicos');
    Route::get('paneles-publicos/create', PublicInfoPanelEditor::class)
        ->name('centinela.admin.paneles-publicos.create');
    Route::get('paneles-publicos/{panel}', PublicInfoPanelShow::class)
        ->whereNumber('panel')
        ->name('centinela.admin.paneles-publicos.show');
    Route::get('paneles-publicos/{panel}/edit', PublicInfoPanelEditor::class)
        ->whereNumber('panel')
        ->name('centinela.admin.paneles-publicos.edit');
    // SDD-307: compose de mensajería en Voyager (Livewire full-page; admin global / CG su SS).
    Route::get('mensajeria', MessagingCampaignComposer::class)
        ->name('centinela.admin.mensajeria');
});

Route::group(['prefix' => 'admin'], function () {
    Voyager::routes();

    // Sobrescribir GET /admin/settings con nuestro componente Livewire full-page.
    // DEBE ir DESPUES de Voyager::routes() porque RouteCollection usa asignacion
    // directa de arrays (no push), asi que la ULTIMA ruta registrada gana.
    Route::get('settings', SettingsManager::class)
        ->middleware('admin.user')
        ->name('voyager.settings.index');

    // Mismo requisito: reemplazan acciones de Voyager cuyo SchemaManager falla en 1.8.
    Route::middleware('admin.user')->group(function (): void {
        Route::get('bread/{table}/create', [CentinelaVoyagerBreadController::class, 'create'])
            ->name('voyager.bread.create');
        Route::get('bread/{table}/edit', [CentinelaVoyagerBreadController::class, 'edit'])
            ->name('voyager.bread.edit');
        Route::resource('database', CentinelaVoyagerDatabaseController::class)
            ->names('voyager.database');
    });

    Route::get('dashboard/{section}/{slug?}', NonAdminDashboardDetail::class)
        ->middleware(['auth', VoyagerAdminMiddleware::class])
        ->name('centinela.dashboard.detail');

    Route::middleware(['auth', VoyagerAdminMiddleware::class])->group(function (): void {
        Route::post('establecimientos/sync-national', [CenCenterController::class, 'syncNational'])
            ->name('voyager.establecimientos.sync-national');
        Route::post('establecimientos/sync-national-full', [CenCenterController::class, 'syncNationalFull'])
            ->name('voyager.establecimientos.sync-national-full');
        Route::post('establecimientos/{id}/notes', [CenCenterController::class, 'storeNote'])
            ->name('voyager.establecimientos.notes.store');
        Route::post('establecimientos/{id}/merge-codigo', [CenCenterController::class, 'mergeCodigo'])
            ->name('voyager.establecimientos.merge-codigo');
        Route::post('establecimientos/glosa-equivalences', [CenCenterController::class, 'storeGlosaEquivalence'])
            ->name('voyager.establecimientos.glosa-equivalences.store');
        Route::get('establecimientos/glosa-equivalences/export', [CenCenterController::class, 'exportGlosaEquivalences'])
            ->name('voyager.establecimientos.glosa-equivalences.export');
        Route::post('establecimientos/sync-issues/{issue}/promote-glosa-equivalence', [CenCenterController::class, 'promoteGlosaEquivalenceFromIssue'])
            ->name('voyager.establecimientos.sync-issues.promote-glosa-equivalence');
        Route::post('establecimientos/glosa-equivalences/{equivalence}/deactivate', [CenCenterController::class, 'deactivateGlosaEquivalence'])
            ->name('voyager.establecimientos.glosa-equivalences.deactivate');
    });

    /*
     * PHPUnit + RefreshDatabase: la primera resolución de rutas puede ocurrir con `data_types` vacío,
     * así que Voyager no registra el BREAD. Registramos el conjunto equivalente sólo cuando faltan.
     *
     * @see \Tests\Feature\Documents\DocumentCrudTest
     * @see \Tests\Feature\Comges\ComgesBrowseUnifiedTest
     * @see \Tests\Feature\Comges\ComgesIndicatorReadViewTest
     */
    if (
        App::runningUnitTests()
        && ! Route::has('voyager.documentos.index')
    ) {
        Route::middleware(['auth', VoyagerAdminMiddleware::class])->group(function (): void {
            Route::get('documentos', [DocumentController::class, 'index'])->name('voyager.documentos.index');
            Route::post('documentos', [DocumentController::class, 'store'])->name('voyager.documentos.store');
            Route::post('documentos/action', [DocumentController::class, 'action'])->name('voyager.documentos.action');
            Route::get('documentos/create', [DocumentController::class, 'create'])->name('voyager.documentos.create');
            Route::get('documentos/order', [DocumentController::class, 'order'])->name('voyager.documentos.order');
            Route::post('documentos/order', [DocumentController::class, 'update_order'])->name('voyager.documentos.update_order');
            Route::get('documentos/relation', [DocumentController::class, 'relation'])->name('voyager.documentos.relation');
            Route::post('documentos/remove', [DocumentController::class, 'remove_media'])->name('voyager.documentos.media.remove');
            Route::get('documentos/{id}', [DocumentController::class, 'show'])->name('voyager.documentos.show');
            Route::match(['put', 'patch'], 'documentos/{id}', [DocumentController::class, 'update'])->name('voyager.documentos.update');
            Route::delete('documentos/{id}', [DocumentController::class, 'destroy'])->name('voyager.documentos.destroy');
            Route::get('documentos/{id}/edit', [DocumentController::class, 'edit'])->name('voyager.documentos.edit');
            Route::get('documentos/{id}/restore', [DocumentController::class, 'restore'])->name('voyager.documentos.restore');
        });
    }

    if (
        App::runningUnitTests()
        && ! Route::has('voyager.compromisos.index')
    ) {
        Route::middleware(['auth', VoyagerAdminMiddleware::class])->group(function (): void {
            Route::get('compromisos', [ComCommitmentController::class, 'index'])->name('voyager.compromisos.index');
            Route::post('compromisos', [ComCommitmentController::class, 'store'])->name('voyager.compromisos.store');
            Route::post('compromisos/action', [ComCommitmentController::class, 'action'])->name('voyager.compromisos.action');
            Route::get('compromisos/create', [ComCommitmentController::class, 'create'])->name('voyager.compromisos.create');
            Route::get('compromisos/order', [ComCommitmentController::class, 'order'])->name('voyager.compromisos.order');
            Route::post('compromisos/order', [ComCommitmentController::class, 'update_order'])->name('voyager.compromisos.update_order');
            Route::get('compromisos/estructura', [ComCommitmentController::class, 'structureOrder'])->name('voyager.compromisos.structure-order');
            Route::get('compromisos/relation', [ComCommitmentController::class, 'relation'])->name('voyager.compromisos.relation');
            Route::post('compromisos/remove', [ComCommitmentController::class, 'remove_media'])->name('voyager.compromisos.media.remove');
            Route::get('compromisos/{id}', [ComCommitmentController::class, 'show'])->name('voyager.compromisos.show');
            Route::match(['put', 'patch'], 'compromisos/{id}', [ComCommitmentController::class, 'update'])->name('voyager.compromisos.update');
            Route::delete('compromisos/{id}', [ComCommitmentController::class, 'destroy'])->name('voyager.compromisos.destroy');
            Route::get('compromisos/{id}/edit', [ComCommitmentController::class, 'edit'])->name('voyager.compromisos.edit');
            Route::get('compromisos/{id}/restore', [ComCommitmentController::class, 'restore'])->name('voyager.compromisos.restore');
        });
    }

    if (
        App::runningUnitTests()
        && ! Route::has('voyager.indicadores-comges.show')
    ) {
        Route::middleware(['auth', VoyagerAdminMiddleware::class])->group(function (): void {
            Route::get('indicadores-comges', [ComIndicatorController::class, 'index'])->name('voyager.indicadores-comges.index');
            Route::post('indicadores-comges', [ComIndicatorController::class, 'store'])->name('voyager.indicadores-comges.store');
            Route::post('indicadores-comges/action', [ComIndicatorController::class, 'action'])->name('voyager.indicadores-comges.action');
            Route::get('indicadores-comges/create', [ComIndicatorController::class, 'create'])->name('voyager.indicadores-comges.create');
            Route::get('indicadores-comges/order', [ComIndicatorController::class, 'order'])->name('voyager.indicadores-comges.order');
            Route::post('indicadores-comges/order', [ComIndicatorController::class, 'update_order'])->name('voyager.indicadores-comges.update_order');
            Route::get('indicadores-comges/relation', [ComIndicatorController::class, 'relation'])->name('voyager.indicadores-comges.relation');
            Route::post('indicadores-comges/remove', [ComIndicatorController::class, 'remove_media'])->name('voyager.indicadores-comges.media.remove');
            Route::get('indicadores-comges/{id}', [ComIndicatorController::class, 'show'])->name('voyager.indicadores-comges.show');
            Route::match(['put', 'patch'], 'indicadores-comges/{id}', [ComIndicatorController::class, 'update'])->name('voyager.indicadores-comges.update');
            Route::delete('indicadores-comges/{id}', [ComIndicatorController::class, 'destroy'])->name('voyager.indicadores-comges.destroy');
            Route::get('indicadores-comges/{id}/edit', [ComIndicatorController::class, 'edit'])->name('voyager.indicadores-comges.edit');
            Route::get('indicadores-comges/{id}/restore', [ComIndicatorController::class, 'restore'])->name('voyager.indicadores-comges.restore');
        });
    }

    if (
        App::runningUnitTests()
        && ! Route::has('voyager.users.store')
    ) {
        Route::middleware(['auth', VoyagerAdminMiddleware::class])->group(function (): void {
            Route::get('users', [CentinelaVoyagerUserController::class, 'index'])->name('voyager.users.index');
            Route::post('users', [CentinelaVoyagerUserController::class, 'store'])->name('voyager.users.store');
            Route::post('users/action', [CentinelaVoyagerUserController::class, 'action'])->name('voyager.users.action');
            Route::get('users/create', [CentinelaVoyagerUserController::class, 'create'])->name('voyager.users.create');
            Route::get('users/order', [CentinelaVoyagerUserController::class, 'order'])->name('voyager.users.order');
            Route::post('users/order', [CentinelaVoyagerUserController::class, 'update_order'])->name('voyager.users.update_order');
            Route::get('users/relation', [CentinelaVoyagerUserController::class, 'relation'])->name('voyager.users.relation');
            Route::post('users/remove', [CentinelaVoyagerUserController::class, 'remove_media'])->name('voyager.users.media.remove');
            Route::get('users/{id}', [CentinelaVoyagerUserController::class, 'show'])->name('voyager.users.show');
            Route::match(['put', 'patch'], 'users/{id}', [CentinelaVoyagerUserController::class, 'update'])->name('voyager.users.update');
            Route::delete('users/{id}', [CentinelaVoyagerUserController::class, 'destroy'])->name('voyager.users.destroy');
            Route::get('users/{id}/edit', [CentinelaVoyagerUserController::class, 'edit'])->name('voyager.users.edit');
            Route::get('users/{id}/restore', [CentinelaVoyagerUserController::class, 'restore'])->name('voyager.users.restore');
        });
    }

    Route::prefix('user-manual')->middleware('module.access:manual_usuario')->group(function () {
        Route::get('/', [UserManualController::class, 'index'])->name('user-manual.index');
        Route::get('context', [UserManualController::class, 'context'])->name('user-manual.context');
        Route::get('{id}', [UserManualController::class, 'show'])->name('user-manual.show');
    });

    Route::post('login', [ExtendVoyagerAuthController::class, 'postLogin'])->name('voyager.postlogin');
    Route::view('panel-establecimientos', 'centinela.centers.index')->name('establecimientos.panel');
    Route::get('api/provinces-by-region/{regionId}', function ($regionId) {
        return CenProvince::where('cen_region_id', $regionId)->orderBy('nombre')->get(['id', 'nombre']);
    })->name('api.provinces.by.region');
    Route::get('api/communes-by-province/{provinceId}', function ($provinceId) {
        return CenCommune::where('cen_province_id', $provinceId)->orderBy('nombre')->get(['id', 'nombre']);
    })->name('api.communes.by.province');

    Route::prefix('adp')->middleware('module.access:adp')->group(function () {
        Route::get('/', [AdpInstrumentController::class, 'index'])->name('adp.instrument.index');
        Route::post('configuracion/toggle-habilitado', [AdpInstrumentController::class, 'toggleHabilitado'])
            ->name('adp.instrument.settings.toggle');

        Route::get('catalogo-indicadores', [AdpIndicatorCatalogController::class, 'index'])->name('adp.catalog.index');
        Route::get('catalogo-indicadores/crear', [AdpIndicatorCatalogController::class, 'create'])->name('adp.catalog.create');
        Route::post('catalogo-indicadores', [AdpIndicatorCatalogController::class, 'store'])->name('adp.catalog.store');
        Route::get('catalogo-indicadores/{item}/editar', [AdpIndicatorCatalogController::class, 'edit'])->name('adp.catalog.edit');
        Route::put('catalogo-indicadores/{item}', [AdpIndicatorCatalogController::class, 'update'])->name('adp.catalog.update');
        Route::delete('catalogo-indicadores/{item}', [AdpIndicatorCatalogController::class, 'destroy'])->name('adp.catalog.destroy');

        Route::get('autoevaluacion/convenios', [AdpSelfEvaluationController::class, 'agreements'])
            ->name('adp.self_evaluation.agreements');
        Route::get('autoevaluacion/convenio/{agreement}', [AdpSelfEvaluationController::class, 'agreement'])
            ->name('adp.self_evaluation.agreement');
        Route::get('autoevaluacion/convenio/{agreement}/periodo/{period}/indicador/{indicator}/carga-mensual', [AdpSelfEvaluationController::class, 'indicatorMonthly'])
            ->name('adp.self_evaluation.indicator.monthly');
        Route::post('autoevaluacion/convenio/{agreement}/periodo/{period}/indicador/{indicator}/carga-mensual', [AdpSelfEvaluationController::class, 'storeMonthly'])
            ->name('adp.self_evaluation.indicator.monthly.store');
        Route::get('autoevaluacion/convenio/{agreement}/periodo/{period}/evaluacion-parcial', [AdpSelfEvaluationController::class, 'midtermForm'])
            ->name('adp.self_evaluation.midterm');
        Route::post('autoevaluacion/convenio/{agreement}/periodo/{period}/evaluacion-parcial', [AdpSelfEvaluationController::class, 'midtermStore'])
            ->name('adp.self_evaluation.midterm.store');
        Route::post('autoevaluacion/convenio/{agreement}/periodo/{period}/recalcular', [AdpSelfEvaluationController::class, 'recalculate'])
            ->name('adp.self_evaluation.period.recalculate');
        Route::get('autoevaluacion/convenio/{agreement}/periodo/{period}/preliminar', [AdpSelfEvaluationController::class, 'preliminaryShow'])
            ->name('adp.self_evaluation.preliminary');
        Route::post('autoevaluacion/convenio/{agreement}/periodo/{period}/preliminar/generar', [AdpSelfEvaluationController::class, 'preliminaryStore'])
            ->name('adp.self_evaluation.preliminary.store');
        Route::get('autoevaluacion/convenio/{agreement}/periodo/{period}/apelaciones', [AdpSelfEvaluationController::class, 'appealsIndex'])
            ->name('adp.self_evaluation.appeals');
        Route::post('autoevaluacion/convenio/{agreement}/periodo/{period}/indicador/{indicator}/apelacion', [AdpSelfEvaluationController::class, 'appealStore'])
            ->name('adp.self_evaluation.appeal.store');
        Route::post('autoevaluacion/convenio/{agreement}/apelacion/{appeal}/resolver', [AdpSelfEvaluationController::class, 'appealResolve'])
            ->name('adp.self_evaluation.appeal.resolve');
        Route::get('autoevaluacion/convenio/{agreement}/periodo/{period}/final', [AdpSelfEvaluationController::class, 'finalShow'])
            ->name('adp.self_evaluation.final');
        Route::post('autoevaluacion/convenio/{agreement}/periodo/{period}/final/publicar', [AdpSelfEvaluationController::class, 'finalPublish'])
            ->name('adp.self_evaluation.final.publish');
        Route::post('autoevaluacion/convenio/{agreement}/periodo/{period}/final/override', [AdpSelfEvaluationController::class, 'finalOverride'])
            ->name('adp.self_evaluation.final.override');
    });

    Route::prefix('{indicatorType:route}')
        ->where(['indicatorType' => 'metas-[0-9]+'])
        ->middleware('module.access.indicator_type')
        ->controller(MetasInstrumentController::class)
        ->group(function () {
            Route::get('/', 'index')->name('metas.instrument.index');

            Route::get('configurar-establecimientos', 'configureEstablishments')
                ->name('metas.instrument.establishments.configure');
            Route::get('configurar-establecimientos/{center}/indicadores', 'configureEstablishmentIndicators')
                ->name('metas.instrument.establishments.indicators');

            Route::resource('indicadores', MetIndicatorCatalogController::class)
                ->only(['create', 'show', 'edit', 'destroy'])
                ->parameters(['indicadores' => 'indicator'])
                ->names([
                    'create' => 'metas.instrument.indicators.create',
                    'show' => 'metas.instrument.indicators.show',
                    'edit' => 'metas.instrument.indicators.edit',
                    'destroy' => 'metas.instrument.indicators.destroy',
                ]);

            Route::get('indicadores/{indicator}/desempeno', [MetIndicatorCatalogController::class, 'desempeno'])
                ->name('metas.instrument.indicators.desempeno');

            Route::get('indicador/{indicator}/carga', 'indicatorCarga')->name('metas.instrument.indicador.carga');
            Route::get('indicador/{indicator}/detalle-datos', 'downloadDataDetail')
                ->name('metas.instrument.indicador.detalle-datos.download')
                ->middleware('throttle:6,1');
            Route::get('indicador/{indicator}/plantilla', 'downloadTemplate')
                ->name('metas.instrument.indicador.plantilla.download')
                ->middleware(['readonly.files:deny-download', 'throttle:6,1']);
            Route::post('indicador/{indicator}/importar', 'import')
                ->name('metas.instrument.indicador.importar')
                ->middleware(['throttle:3,1', 'readonly.files:deny-upload']);
            Route::delete('indicador/{indicator}/datos', 'deleteIndicatorLoadedData')
                ->name('metas.instrument.indicador.datos.destroy');
            Route::get('indicador/{indicator}/establecimiento/{center}', 'show')->name('metas.instrument.indicador.show');
            Route::get('indicador/{indicator}/establecimiento/{center}/unidades', 'centerUnits')->name('metas.instrument.indicador.center.units');
            Route::get('indicador/{indicator}/establecimiento/{center}/unidad/{measurementUnit}', 'showUnit')->name('metas.instrument.indicador.showUnit');
            Route::get('resultados-por-establecimiento/{center}', 'establishmentResults')->name('metas.instrument.establishment.results');
            Route::get('apelacion/{appeal}/adjunto/{attachmentIndex}', 'downloadAppealAttachment')
                ->name('metas.instrument.appeal.attachment.download')
                ->whereNumber('attachmentIndex')
                ->middleware('throttle:15,1');
        });
    Route::get('set-indicator-year/{year_id}', [HelperController::class, 'setIndicatorYear'])->name('setIndicatorYear');
    Route::prefix('comges')->middleware('module.access:comges')->group(function () {
        Route::get('configurar-indicador/{id}', [ComgesController::class, 'configurarIndicador'])->name('configurar-indicador-comges.upsert');
        Route::get('habilita-meta/{com_sensitivity_table_id}', [ComgesController::class, 'habilitaMeta'])->name('habilitar-meta-comges');
        Route::get('decargar-verificables/{com_indicator_cutoff_id}', [ComgesController::class, 'verificablesDownload'])
            ->name('descargar-verificables-comges')
            ->middleware('readonly.files:deny-download');
        Route::post('descarga-masiva-verificables', [ComgesController::class, 'massiveDownload'])
            ->name('descarga-masiva-comges')
            ->middleware('readonly.files:deny-download');
        Route::post('asignacion-masiva-fechas', [ComgesController::class, 'massiveDateCutoff'])->name('asignacion-masiva-fechas');
        Route::post('pestanas-editables/masivo', [ComgesController::class, 'massiveToggleEditableTabs'])
            ->name('comges.pestanas-editables.masivo');
        Route::post('duplicar-corte/{id}', [ComIndicatorCutoffController::class, 'duplicate'])->name('duplicar-corte-comges');
        Route::post('limpieza-masiva-autoevaluaciones', [ComgesController::class, 'clearAutoEvaluations'])->name('limpieza-masiva-autoevaluaciones');
        Route::get('descarga-evaluaciones', [ComgesController::class, 'excelDownload'])
            ->name('descarga-evaluaciones-comges')
            ->middleware('readonly.files:deny-download');
        Route::get('carga-masiva-resultados', [ComgesMassResultsController::class, 'index'])
            ->name('comges.carga-masiva-resultados.index');
        Route::get('carga-masiva-resultados/plantilla', [ComgesMassResultsController::class, 'downloadTemplate'])
            ->name('comges.carga-masiva-resultados.plantilla')
            ->middleware('readonly.files:deny-download');
        Route::post('carga-masiva-resultados/importar', [ComgesMassResultsController::class, 'import'])
            ->name('comges.carga-masiva-resultados.importar')
            ->middleware('readonly.files:deny-upload');
    });

    try {
        foreach (
            Cache::rememberForever('indicator_type_routes', function () {
                return IndicatorType::all();
            }) as $item
        ) {
            if (! is_null($item->view)) {
                Route::view($item->route, $item->view)->name($item->route);
            }
        }
    } catch (InvalidArgumentException $e) {
        throw new InvalidArgumentException("Custom routes hasn't been configured because: ".$e->getMessage(), 1);
    } catch (Exception $e) {
        // do nothing, might just be because table not yet migrated.
    }

    // Rutas de test eliminadas por seguridad

    Route::middleware(['auth', VoyagerAdminMiddleware::class, 'module.access:soporte'])
        ->prefix('soporte')
        ->name('centinela.support.')
        ->group(static function (): void {
            Route::prefix('receptores')->name('receivers.')->group(static function (): void {
                Route::get('/', [SupportTicketReceiverController::class, 'index'])->name('index');
                Route::get('buscar-usuarios', [SupportTicketReceiverController::class, 'searchUsers'])
                    ->name('users.search');
                Route::get('crear', [SupportTicketReceiverController::class, 'create'])->name('create');
                Route::post('/', [SupportTicketReceiverController::class, 'store'])->name('store');
                Route::get('{supportTicketReceiver}/editar', [SupportTicketReceiverController::class, 'edit'])->name('edit');
                Route::put('{supportTicketReceiver}', [SupportTicketReceiverController::class, 'update'])->name('update');
                Route::delete('{supportTicketReceiver}', [SupportTicketReceiverController::class, 'destroy'])->name('destroy');
            });

            Route::get('/', [SupportTicketController::class, 'index'])->name('index');
            Route::get('buscar-solicitantes', [SupportTicketController::class, 'searchRequesters'])
                ->name('requesters.search');
            Route::get('instrumentos-disponibles', [SupportTicketController::class, 'availableInstruments'])
                ->name('instruments.available');
            Route::get('crear', [SupportTicketController::class, 'create'])->name('create');
            Route::post('/', [SupportTicketController::class, 'store'])->name('store');
            Route::get('{supportTicket}', [SupportTicketController::class, 'show'])->name('show');
            Route::get('{supportTicket}/adjunto', [SupportTicketController::class, 'downloadAttachment'])->name('attachment.download');
            Route::post('{supportTicket}/mensajes', [SupportTicketController::class, 'storeMessage'])->name('messages.store');
            Route::patch('{supportTicket}/estado', [SupportTicketController::class, 'updateStatus'])->name('status.update');
            Route::patch('{supportTicket}/asignacion', [SupportTicketController::class, 'updateAssign'])->name('assign.update');
            Route::post('{supportTicket}/github-issue', [SupportTicketController::class, 'storeGithubIssue'])->name('github.store');
        });

    Route::group(['prefix' => 'acerca-de'], function () {
        Route::get('/', [AcercaDeController::class, 'show'])->name('acerca-de.index');
        Route::get('/modulos', [AcercaDeController::class, 'show'])->defaults('slug', 'modulos')->name('acerca-de.modulos');
        Route::get('/roles', [AcercaDeController::class, 'show'])->defaults('slug', 'roles')->name('acerca-de.roles');
        Route::get('/ciclo', fn () => redirect()->route('acerca-de.ciclo-comges'));
        Route::get('/ciclo-comges', [AcercaDeController::class, 'show'])->defaults('slug', 'ciclo-comges')->name('acerca-de.ciclo-comges');
        Route::get('/ciclo-metas', [AcercaDeController::class, 'show'])->defaults('slug', 'ciclo-metas')->name('acerca-de.ciclo-metas');
        Route::get('/adopcion', [AcercaDeController::class, 'show'])->defaults('slug', 'adopcion')->name('acerca-de.adopcion');
        Route::get('/tecnica', [AcercaDeController::class, 'show'])->defaults('slug', 'tecnica')->name('acerca-de.tecnica');
        Route::get('/releases', [ComgesController::class, 'showReleases'])->name('acerca-de.releases');
    });
})->middleware(['indicator.year', 'platform.usage', 'readonly.files:voyager-file-ops', 'upload.size-limit']);

Auth::routes([
    'register' => false,
    'reset' => true,
    'confirm' => false,
    'verify' => false,
    'login' => false,
]);
