<?php

namespace App\Livewire\MetasInstrument\Concerns;

use App\Enums\IndicatorType\EtapaVigente;
use App\Models\IndicatorTypeSetting;
use App\Models\MetIndicatorConfiguration;
use App\Models\User;
use App\Models\Year;
use App\Services\Metas\MetStageAccessService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

trait InteractsWithMetStageCapture
{
    protected function authUser(): User
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }

    protected function currentIndicatorYear(): Year
    {
        $year = session('indicator_year');
        if (! $year instanceof Year) {
            abort(500, 'Año de indicador no definido en sesión.');
        }

        return $year;
    }

    protected function currentEtapaVigente(): EtapaVigente
    {
        $setting = IndicatorTypeSetting::getData($this->indicatorTypeId);

        return $setting->etapa_vigente;
    }

    protected function canWriteStage(EtapaVigente $requiredEtapa): bool
    {
        if (! $this->roleCanWriteStage($requiredEtapa)) {
            return false;
        }

        return $this->currentEtapaVigente() === $requiredEtapa;
    }

    /**
     * Permiso de rol para la etapa (sin exigir que sea la etapa vigente).
     * Sirve para no mostrar ayudas/alertas de edición a roles de solo lectura.
     */
    protected function roleCanWriteStage(EtapaVigente $requiredEtapa): bool
    {
        return app(MetStageAccessService::class)->canWriteForEtapa($this->authUser(), $requiredEtapa);
    }

    /**
     * @return Collection<int, MetIndicatorConfiguration>
     */
    protected function convenidoConfigurationsForCenter(int $centerId): Collection
    {
        return MetIndicatorConfiguration::query()
            ->currentYear()
            ->where('cen_center_id', $centerId)
            ->where('seleccionado', true)
            ->where('aplica', true)
            ->whereNull('met_measurement_unit_id')
            ->whereHas('metIndicator', fn ($q) => $q->where('indicator_type_id', $this->indicatorTypeId))
            ->with('metIndicator')
            ->orderBy('met_indicator_id')
            ->get();
    }
}
