<?php

namespace App\Providers;

use App\FormFields\JsonFormField;
use App\FormFields\ReadOnlyFormField;
use App\Helpers\Helper;
use App\Helpers\ModuleAccess;
use App\Http\Middleware\EnsureIndicatorYearIsSet;
use App\Models\IndicatorType;
use App\Models\Role;
use App\Models\Year;
use App\Services\Alerts\CentinelaVisualAlertService;
use App\Services\Alerts\Providers\ComgesCommitmentBrowseProvider;
use App\Services\Alerts\Providers\ComgesDashboardListProvider;
use App\Services\Metas\MetFieldSsConfigService;
use App\Services\Metas\MetIndicatorCalculationDataScopeService;
use App\Services\Metas\MetIndicatorDesempenoByEstablishmentService;
use App\Services\Metas\MetIndicatorLastLoadedMonthService;
use App\Services\Releases\DeployedReleaseState;
use App\Support\Carbon\StrftimeFormatConverter;
use App\Support\Configuration\AdminBackgroundImage;
use App\Support\Configuration\ConfigurationService;
use App\Support\Voyager\YearDropdownCatalog;
use App\View\Composers\VoyagerDashboardComposer;
use App\View\Composers\VoyagerMasterComposer;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use TCG\Voyager\Events\MenuDisplay;
use TCG\Voyager\Events\SettingUpdated;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Models\MenuItem;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind('TCG\Voyager\Models\Role', Role::class);
        $this->app->bind('helper', fn () => new Helper);
        $this->app->singleton(ConfigurationService::class, fn () => new ConfigurationService);
        $this->app->singleton(MetFieldSsConfigService::class);
        $this->app->singleton(MetIndicatorCalculationDataScopeService::class);
        $this->app->singleton(MetIndicatorLastLoadedMonthService::class);
        $this->app->singleton(MetIndicatorDesempenoByEstablishmentService::class);

        $this->app->singleton(CentinelaVisualAlertService::class, function (): CentinelaVisualAlertService {
            $service = new CentinelaVisualAlertService;
            $service->register(new ComgesCommitmentBrowseProvider);
            $service->register(new ComgesDashboardListProvider);

            return $service;
        });

        Voyager::addFormField(ReadOnlyFormField::class);
        Voyager::addFormField(JsonFormField::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Carbon 3: restaurar API Voyager formatLocalized (strftime → translatedFormat).
        $formatLocalized = function (string $format): string {
            /** @var Carbon|CarbonImmutable $this */
            return $this->copy()
                ->locale(app()->getLocale())
                ->translatedFormat(StrftimeFormatConverter::toPhpFormat($format));
        };
        Carbon::macro('formatLocalized', $formatLocalized);
        CarbonImmutable::macro('formatLocalized', $formatLocalized);

        if (! $this->app->runningInConsole() || $this->app->runningUnitTests()) {
            try {
                $displayVersion = $this->app->make(DeployedReleaseState::class)->displayVersion();
                if ($displayVersion !== '') {
                    Config::set('app.version', $displayVersion);
                }
            } catch (\Throwable) {
                // Mantener APP_VERSION si BD no está disponible aún.
            }
        }

        // Protección contra comandos destructivos en producción
        if (App::environment('production')) {
            DB::whenQueryingForLongerThan(5000, function () {
                logger()->warning('Query ejecutándose por más de 5 segundos en producción');
            });
        }

        // EnsureIndicatorYearIsSet se aplica también en requests AJAX de Livewire.
        // Sin esto, si la sesión expira durante la interacción, los componentes
        // fallan con 500 al llamar session('indicator_year')->id en método render/action.
        Livewire::addPersistentMiddleware([
            EnsureIndicatorYearIsSet::class,
        ]);

        // Configuración de ruta personalizada para Livewire solo si APP_URL tiene subdirectorio
        $url = config('app.url');
        $path = parse_url($url, PHP_URL_PATH);

        // Solo personalizar si hay subdirectorio en la URL (ej: /centinela)
        if (! empty($path) && $path !== '/') {
            Livewire::setUpdateRoute(function ($handle) use ($path) {
                $part = trim($path, '/').'/livewire/update';

                return Route::post($part, $handle)->name('livewire.update');
            });
        }

        View::composer(
            ['voyager::index'],
            VoyagerDashboardComposer::class
        );

        View::composer(
            ['voyager::*'],
            VoyagerMasterComposer::class
        );

        // Cargar configuracion desde BD sobre config/*.php (web y Artisan).
        // loadSettings() ya tolera BD no disponible (p. ej. migrate en instalación nueva).
        $this->app->make(ConfigurationService::class)->overrideConfig();

        // Invalidar cache y recargar config al actualizar un setting Voyager
        $this->app->make('events')->listen(SettingUpdated::class, function () {
            AdminBackgroundImage::invalidate();
            $this->app->make(ConfigurationService::class)->invalidate();
            $this->app->make(ConfigurationService::class)->overrideConfig();
        });

        // Invalidar cache de rutas dinamicas al crear/editar/eliminar un IndicatorType
        IndicatorType::saved(fn () => Cache::forget('indicator_type_routes'));
        IndicatorType::deleted(fn () => Cache::forget('indicator_type_routes'));

        Year::saved(fn () => YearDropdownCatalog::invalidate());
        Year::deleted(fn () => YearDropdownCatalog::invalidate());

        // Ocultar item "Settings" del menu Voyager y agregar "Configuracion" (reemplazado por nuestro SettingsManager Livewire)
        $this->app->make('events')->listen(MenuDisplay::class, function ($event) {
            if ($event->menu->name !== 'admin') {
                return;
            }

            // Mapeo de rutas de menu a module keys para filtro por SS
            $menuRouteModuleMap = [
                'centinela.support.index' => 'soporte',
            ];

            // Filtrar items del menu segun ModuleAccess (no-admin)
            $event->menu->items = $event->menu->items->reject(function ($item) use ($menuRouteModuleMap) {
                if (! $item->route) {
                    return false;
                }

                $moduleKey = $menuRouteModuleMap[$item->route] ?? null;
                if ($moduleKey === null) {
                    return false;
                }

                return ! ModuleAccess::canAccess($moduleKey);
            });

            // Ocultar item "Settings" original (reemplazado por Configuracion)
            $event->menu->items = $event->menu->items->reject(function ($item) {
                return $item->route === 'voyager.settings.index';
            });

            // Agregar "Configuracion" como item raiz si no existe ya
            $hasConfigItem = $event->menu->items->contains(function ($item) {
                return $item->route === 'voyager.settings.index';
            });

            if (! $hasConfigItem) {
                $configItem = new MenuItem([
                    'title' => 'Configuracion',
                    'route' => 'voyager.settings.index',
                    'icon_class' => 'voyager-settings',
                    'order' => 99,
                    'target' => '_self',
                    'color' => '',
                ]);
                $configItem->exists = false;
                $event->menu->items->push($configItem);
            }
        });
    }
}
