<?php

namespace App\Services\Alerts;

use App\Contracts\Alerts\VisualAlertProviderInterface;
use App\Support\Alerts\AlertContext;
use App\Support\Alerts\VisualAlert;
use App\Support\Alerts\VisualAlertPlacement;
use Illuminate\Support\Collection;

class CentinelaVisualAlertService
{
    /** @var list<VisualAlertProviderInterface> */
    private array $providers = [];

    public function register(VisualAlertProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * @param  iterable<mixed>  $subjects
     * @return Collection<int, VisualAlert>
     */
    public function collectForModule(
        AlertContext $context,
        string $moduleValue,
        iterable $subjects,
    ): Collection {
        $alerts = collect();

        foreach ($this->providers as $provider) {
            if ($provider->module()->value !== $moduleValue) {
                continue;
            }

            if (! $provider->supports($context)) {
                continue;
            }

            $alerts = $alerts->merge($provider->collect($context, $subjects));
        }

        return $this->applyRoleFilter($context, $alerts);
    }

    /**
     * @return list<string>
     */
    public function requiredEagerLoads(AlertContext $context, string $moduleValue): array
    {
        $loads = [];

        foreach ($this->providers as $provider) {
            if ($provider->module()->value !== $moduleValue) {
                continue;
            }

            if (! $provider->supports($context)) {
                continue;
            }

            foreach ($provider->requiredEagerLoads($context) as $path) {
                $loads[] = $path;
            }
        }

        return array_values(array_unique($loads));
    }

    /**
     * @param  Collection<int, VisualAlert>  $alerts
     * @return Collection<int, VisualAlert>
     */
    public function applyRoleFilter(AlertContext $context, Collection $alerts): Collection
    {
        $roleName = $context->roleName();

        return $alerts
            ->filter(fn (VisualAlert $alert): bool => $alert->visibleForRoles($roleName))
            ->values();
    }

    /**
     * @param  Collection<int, VisualAlert>  $alerts
     * @return array<string, list<VisualAlert>>
     */
    public function groupByCell(Collection $alerts, int $maxPerCell = 3): array
    {
        $grouped = [];

        foreach ($alerts as $alert) {
            if ($alert->placement === VisualAlertPlacement::DashboardList) {
                continue;
            }

            $key = $alert->groupKey();
            $grouped[$key] ??= [];
            $grouped[$key][] = $alert;
        }

        foreach ($grouped as $key => $items) {
            usort($items, fn (VisualAlert $a, VisualAlert $b): int => $b->priority <=> $a->priority);
            $grouped[$key] = array_slice($items, 0, $maxPerCell);
        }

        return $grouped;
    }

    /**
     * @param  Collection<int, VisualAlert>  $alerts
     * @return list<array{severity: string, title: string, body: string, url: string}>
     */
    public function toDashboardListPayload(Collection $alerts): array
    {
        $seen = [];
        $out = [];

        foreach ($alerts as $alert) {
            if ($alert->placement !== VisualAlertPlacement::DashboardList) {
                continue;
            }

            $body = trim(($alert->detail ?? ''));
            $dedupeKey = $alert->title.'|'.($alert->url ?? '').'|'.$body;

            if (isset($seen[$dedupeKey])) {
                continue;
            }

            $seen[$dedupeKey] = true;
            $out[] = [
                'severity' => $alert->severity->value,
                'title' => $alert->title,
                'body' => $body,
                'url' => (string) ($alert->url ?? ''),
            ];
        }

        return $out;
    }
}
