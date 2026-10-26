<?php

namespace App\Support\Dashboard;

use App\Helpers\Helper;

/**
 * Semáforo unificado del dashboard operativo (escala en %).
 */
final class DashboardAmbitoSemaforoPresenter
{
    public function value(?float $percent): string
    {
        return Helper::muestra_semaforo($percent, 75);
    }
}
