<?php

namespace App\Services\Comges;

use App\Models\ComCommitment;
use App\Models\User;
use App\Models\Year;
use App\Services\Alerts\CentinelaVisualAlertService;
use App\Support\Alerts\AlertContext;
use App\Support\Alerts\AlertSurface;
use App\Support\Alerts\VisualAlert;
use App\Support\Alerts\VisualAlertModule;
use App\Support\Comges\ComgesBrowseContext;
use Illuminate\Support\Collection;

class ComgesCommitmentAlertPresenter
{
    /**
     * @param  Collection<int, ComCommitment>  $commitments
     * @return array<string, list<VisualAlert>>
     */
    public function forBrowse(ComgesBrowseContext $context, Collection $commitments): array
    {
        if (
            ! $context->useScoreScale
            || $context->isFiltering()
            || ! $context->user instanceof User
            || ! $context->year instanceof Year
        ) {
            return [];
        }

        $alertContext = new AlertContext(
            user: $context->user,
            year: $context->year,
            surface: AlertSurface::Browse,
            usesScoreScale: true,
            cenHieDependencyId: $context->cenHieDependencyId,
            comgesEtapaVigente: $context->comgesEtapaVigente,
        );

        /** @var CentinelaVisualAlertService $alertService */
        $alertService = app(CentinelaVisualAlertService::class);
        $alerts = $alertService->collectForModule(
            $alertContext,
            VisualAlertModule::Comges->value,
            $commitments,
        );

        return $alertService->groupByCell($alerts);
    }
}
