<?php

namespace App\Support\Alerts;

use App\Enums\IndicatorType\EtapaVigente;
use App\Models\User;
use App\Models\Year;

final readonly class AlertContext
{
    public function __construct(
        public User $user,
        public Year $year,
        public AlertSurface $surface,
        public bool $usesScoreScale,
        public ?int $cenHieDependencyId = null,
        public ?EtapaVigente $comgesEtapaVigente = null,
    ) {}

    public function roleName(): string
    {
        return (string) ($this->user->role?->name ?? '');
    }
}
