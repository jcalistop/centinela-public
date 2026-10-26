<?php

namespace App\Support\Cen\Dto;

final class CenCatalogGlosaResolution
{
    public function __construct(
        public readonly string $persistedValue,
        public readonly ?string $apiGlosaRaw,
        public readonly ?string $normalizedAfterSyntax,
        public readonly bool $equivalenceApplied,
        public readonly ?string $rule = null,
        public readonly bool $isUnknown = false,
    ) {}
}
