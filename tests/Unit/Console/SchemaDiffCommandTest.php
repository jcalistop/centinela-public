<?php

namespace Tests\Unit\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SchemaDiffCommandTest extends TestCase
{
    public function test_requires_target_option(): void
    {
        $exit = Artisan::call('centinela:schema-diff');

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('--target=', Artisan::output());
    }

    public function test_rejects_non_mysql_connections(): void
    {
        $exit = Artisan::call('centinela:schema-diff', [
            '--reference' => 'sqlite',
            '--target' => 'sqlite',
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('debe ser MySQL', Artisan::output());
    }
}
