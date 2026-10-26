<?php

namespace Tests\Unit\Admin;

use App\Support\Admin\AdminUsageDashboardService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminUsageDashboardServiceTest extends TestCase
{
    private AdminUsageDashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AdminUsageDashboardService::class);
    }

    #[DataProvider('tabResolutionProvider')]
    public function test_resolve_tab_normalizes_input(mixed $input, string $expected): void
    {
        $this->assertSame($expected, $this->service->resolveTab($input));
    }

    /**
     * @return array<string, array{0: mixed, 1: string}>
     */
    public static function tabResolutionProvider(): array
    {
        return [
            'default uso' => [null, 'uso'],
            'uso explicit' => ['uso', 'uso'],
            'tablas' => ['tablas', 'tablas'],
            'salud' => ['salud', 'salud'],
            'invalid string' => ['invalid', 'uso'],
            'numeric' => [123, 'uso'],
        ];
    }
}
