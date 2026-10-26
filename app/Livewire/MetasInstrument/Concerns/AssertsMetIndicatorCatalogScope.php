<?php

namespace App\Livewire\MetasInstrument\Concerns;

use App\Models\IndicatorType;
use App\Models\MetIndicator;
use App\Models\User;
use App\Models\Year;
use Illuminate\Support\Facades\Auth;

trait AssertsMetIndicatorCatalogScope
{
    protected function authorizeParametrizador(): void
    {
        if (! $this->authCatalogUser()->hasRole(['parametrizador', 'admin'])) {
            abort(403);
        }
    }

    /**
     * Lectura de ficha (show): roles con botón Ver en lista + parametrizador (gestión en ficha SDD-119).
     */
    protected function authorizeCatalogShow(): void
    {
        if (! $this->authCatalogUser()->hasRole(['parametrizador', 'control-gestion', 'admin', 'referente', 'visualizador'])) {
            abort(403);
        }
    }

    protected function authorizeDesempenoView(): void
    {
        if (! $this->authCatalogUser()->hasRole(['control-gestion', 'admin', 'referente', 'visualizador'])) {
            abort(403);
        }
    }

    protected function authCatalogUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    protected function assertMetasInstrumentIndicatorType(IndicatorType $indicatorType): void
    {
        if (
            $indicatorType->view !== null
            || $indicatorType->route === null
            || ! str_starts_with((string) $indicatorType->route, 'metas-')
        ) {
            abort(404);
        }
    }

    protected function assertIndicatorBelongsToInstrument(MetIndicator $indicator, IndicatorType $indicatorType): void
    {
        if ((int) $indicator->indicator_type_id !== (int) $indicatorType->id) {
            abort(404);
        }
    }

    protected function assertIndicatorYearScope(MetIndicator $indicator): void
    {
        if ((int) $indicator->year_id !== $this->currentIndicatorYear()->id) {
            abort(404);
        }
    }

    protected function assertIndicatorInScope(MetIndicator $indicator, IndicatorType $indicatorType): void
    {
        $this->assertIndicatorBelongsToInstrument($indicator, $indicatorType);
        $this->assertIndicatorYearScope($indicator);
        $this->assertIndicatorActiveForCatalogRole($indicator);
    }

    protected function userCanViewInactiveCatalogIndicators(): bool
    {
        return $this->authCatalogUser()->hasRole(['parametrizador', 'admin']);
    }

    /**
     * Indicadores inactivos (estado=false) solo visibles para parametrizador y admin.
     */
    protected function assertIndicatorActiveForCatalogRole(MetIndicator $indicator): void
    {
        if ($indicator->estado || $this->userCanViewInactiveCatalogIndicators()) {
            return;
        }

        abort(404);
    }

    protected function currentIndicatorYear(): Year
    {
        $year = session()->get('indicator_year');
        abort_unless($year instanceof Year, 500);

        return $year;
    }
}
