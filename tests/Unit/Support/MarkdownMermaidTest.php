<?php

namespace Tests\Unit\Support;

use App\Support\MarkdownMermaid;
use Tests\TestCase;

class MarkdownMermaidTest extends TestCase
{
    /** @test */
    public function to_html_renders_root_relative_image_without_subdirectory(): void
    {
        config(['app.url' => 'https://centinela-public.test']);

        $html = MarkdownMermaid::toHtml('![Ciclo](/docs/diagrams/ciclo-comges.svg)');

        $this->assertStringContainsString('src="/docs/diagrams/ciclo-comges.svg"', $html);
        $this->assertStringNotContainsString('/centinela/docs/', $html);
    }

    /** @test */
    public function to_html_prefixes_root_relative_urls_when_app_url_has_subdirectory(): void
    {
        config(['app.url' => 'http://localhost/centinela']);

        $html = MarkdownMermaid::toHtml(
            "![Ciclo](/docs/diagrams/ciclo-comges.svg)\n\n[Soporte](/admin/soporte)"
        );

        $this->assertStringContainsString('src="/centinela/docs/diagrams/ciclo-comges.svg"', $html);
        $this->assertStringContainsString('href="/centinela/admin/soporte"', $html);
        $this->assertStringNotContainsString('src="/docs/diagrams/ciclo-comges.svg"', $html);
        $this->assertStringNotContainsString('href="/admin/soporte"', $html);
    }

    /** @test */
    public function rewrite_does_not_double_prefix_or_touch_protocol_relative_urls(): void
    {
        config(['app.url' => 'http://localhost/centinela']);

        $html = '<img src="/centinela/docs/diagrams/x.svg" />'
            .'<a href="//cdn.example.com/a.png">x</a>'
            .'<a href="https://example.com/y">y</a>';

        $rewritten = MarkdownMermaid::rewriteAppRelativeUrls($html);

        $this->assertSame($html, $rewritten);
    }
}
