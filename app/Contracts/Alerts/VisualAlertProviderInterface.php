<?php

namespace App\Contracts\Alerts;

use App\Support\Alerts\AlertContext;
use App\Support\Alerts\VisualAlert;
use App\Support\Alerts\VisualAlertModule;
use Illuminate\Support\Collection;

interface VisualAlertProviderInterface
{
    public function module(): VisualAlertModule;

    public function supports(AlertContext $context): bool;

    /**
     * @return list<string> Dot-notation eager load paths for Eloquent
     */
    public function requiredEagerLoads(AlertContext $context): array;

    /**
     * @param  iterable<mixed>  $subjects  Root entities (e.g. Collection of ComCommitment)
     * @return Collection<int, VisualAlert>
     */
    public function collect(AlertContext $context, iterable $subjects): Collection;
}
