<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Configuration;

use App\Support\Configuration\InstanceIdentityMarkdown;
use Tests\TestCase;

class InstanceIdentityMarkdownTest extends TestCase
{
    public function test_replaces_known_tokens(): void
    {
        config([
            'centinela.instance.ss_display_name' => 'SS Demo',
            'centinela.instance.ss_url' => 'https://demo.gob.cl/',
            'centinela.instance.unit_name' => 'Unidad Demo',
        ]);

        $out = InstanceIdentityMarkdown::apply(
            'Op: {{instance.ss_display_name}} / {{instance.unit_name}} / {{instance.ss_url}}'
        );

        $this->assertSame('Op: SS Demo / Unidad Demo / https://demo.gob.cl/', $out);
    }

    public function test_unknown_token_is_removed_safely(): void
    {
        $out = InstanceIdentityMarkdown::apply('X {{instance.unknown_key}} Y');

        $this->assertSame('X  Y', $out);
        $this->assertStringNotContainsString('{{', $out);
    }

    public function test_falls_back_to_ss_de_prueba_when_empty(): void
    {
        config(['centinela.instance.ss_display_name' => '']);

        $out = InstanceIdentityMarkdown::apply('{{instance.ss_display_name}}');

        $this->assertSame('SS de prueba', $out);
    }
}
