<?php

namespace App\Services\Cen\Dto;

final class MappedNationalEstablishment
{
    /**
     * @param  array<string, mixed>  $centerAttributes
     * @param  array<string, mixed>|null  $addressAttributes
     */
    public function __construct(
        public readonly string $codigo,
        public readonly array $centerAttributes,
        public readonly ?array $addressAttributes,
        public readonly bool $skipped,
        public readonly ?string $skipReason = null,
        /** @var list<array{code: string, message: string, severity: string}> */
        public readonly array $warnings = [],
    ) {}
}
