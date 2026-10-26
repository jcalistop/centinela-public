<?php

namespace App\Services\Notifications;

final class IndicatorNoteMailData
{
    public function __construct(
        public readonly string $moduleLabel,
        public readonly string $indicatorClave,
        public readonly string $indicatorNombre,
        public readonly string $contextLabel,
        public readonly string $servicioSaludNombre,
        public readonly string $ano,
        public readonly string $noteText,
        public readonly string $authorName,
        public readonly string $registeredAt,
        public readonly string $bitacoraUrl,
    ) {}
}
