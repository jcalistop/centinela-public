<?php

namespace App\Services\Notifications;

final class ReferenteAssignmentMailData
{
    public function __construct(
        public readonly string $moduleLabel,
        public readonly string $indicatorClave,
        public readonly string $indicatorNombre,
        public readonly string $contextLabel,
        public readonly string $servicioSaludNombre,
        public readonly int $ano,
        public readonly string $showUrl,
        public readonly ?string $cargaUrl = null,
    ) {}
}
