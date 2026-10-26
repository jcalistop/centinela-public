<?php

namespace App\Livewire\ModulosPorServicio;

use App\Helpers\ModuleAccess;
use App\Models\CenHieDependency;
use App\Models\ServiceModuleToggle;
use App\Models\User;
use App\Support\Configuration\ConfigurationService;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

class Grid extends Component
{
    /** @var Collection<int, CenHieDependency> */
    public $ssList;

    /** @var array<int, string> */
    public array $modules;

    /** @var array<int, array<string, bool>> */
    public array $toggles = [];

    /**
     * module_key => true cuando el IndicatorType del catálogo tiene estado Off (capa 1).
     *
     * @var array<string, bool>
     */
    public array $catalogDisabledModules = [];

    public function mount(): void
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            abort(403, 'Unauthorized.');
        }
        abort_unless($user->hasPermission('browse_service_module_toggles'), 403);

        $this->modules = ServiceModuleToggle::knownModules();
        $this->catalogDisabledModules = $this->resolveCatalogDisabledModules();

        $this->ssList = CenHieDependency::enabled()->get();

        $allToggles = ServiceModuleToggle::all();
        foreach ($allToggles as $t) {
            $this->toggles[$t->cen_hie_dependency_id][$t->module_key] = (bool) $t->enabled;
        }
    }

    public function toggle(int $ssId, string $moduleKey): void
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            abort(403, 'Unauthorized.');
        }
        abort_unless($user->hasPermission('edit_service_module_toggles'), 403);

        if ($this->isCatalogDisabled($moduleKey)) {
            abort(403, 'El instrumento está deshabilitado en el catálogo. Reactívelo en tipos de indicador antes de cambiar toggles por Servicio.');
        }

        $currentValue = $this->toggles[$ssId][$moduleKey] ?? true;
        $newValue = ! $currentValue;

        ServiceModuleToggle::updateOrCreate(
            [
                'cen_hie_dependency_id' => $ssId,
                'module_key' => $moduleKey,
            ],
            [
                'enabled' => $newValue,
            ]
        );

        if ($moduleKey === 'soporte' && $newValue) {
            app(ConfigurationService::class)->setRadioOn('module.soporte_enabled', true);
        }

        $this->toggles[$ssId][$moduleKey] = $newValue;
    }

    public function isCatalogDisabled(string $moduleKey): bool
    {
        return ($this->catalogDisabledModules[$moduleKey] ?? false) === true;
    }

    /**
     * @return array<string, bool>
     */
    private function resolveCatalogDisabledModules(): array
    {
        $disabled = [];

        foreach ($this->modules as $moduleKey) {
            $active = ModuleAccess::isCatalogInstrumentActive($moduleKey);
            if ($active === false) {
                $disabled[$moduleKey] = true;
            }
        }

        return $disabled;
    }

    public function render(): View
    {
        /** @var View $view */
        $view = view('livewire.modulos-por-servicio.grid');

        return $view
            ->extends('voyager::master')
            ->section('content');
    }
}
