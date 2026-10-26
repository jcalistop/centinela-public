<?php

namespace App\Support\ChatIa;

final class PageContext
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public string $routeName,
        public string $path,
        public ?int $indicatorTypeId,
        public ?int $yearId,
        public ?int $cenHieDependencyId,
        public array $extra = [],
    ) {}

    /**
     * @return array{
     *   route_name: string,
     *   path: string,
     *   indicator_type_id: int|null,
     *   year_id: int|null,
     *   cen_hie_dependency_id: int|null,
     *   extra: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'route_name' => $this->routeName,
            'path' => $this->path,
            'indicator_type_id' => $this->indicatorTypeId,
            'year_id' => $this->yearId,
            'cen_hie_dependency_id' => $this->cenHieDependencyId,
            'extra' => $this->extra,
        ];
    }
}
