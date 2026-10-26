<?php

namespace Tests\Unit\Support;

use App\Support\MarkdownToc;
use PHPUnit\Framework\TestCase;

class MarkdownTocTest extends TestCase
{
    public function test_enrich_assigns_unique_ids_and_builds_toc(): void
    {
        $html = '<h2>Primera sección</h2><p>Texto</p><h3>Sub</h3><h2>Primera sección</h2>';
        $result = MarkdownToc::enrich($html);

        $this->assertCount(3, $result['toc']);
        $this->assertSame('primera-seccion', $result['toc'][0]['id']);
        $this->assertSame(2, $result['toc'][0]['level']);
        $this->assertSame('Primera sección', $result['toc'][0]['text']);

        $this->assertSame('sub', $result['toc'][1]['id']);
        $this->assertSame(3, $result['toc'][1]['level']);

        $this->assertSame('primera-seccion-2', $result['toc'][2]['id']);

        $this->assertStringContainsString('id="primera-seccion"', $result['html']);
        $this->assertStringContainsString('id="primera-seccion-2"', $result['html']);
        $this->assertStringContainsString('id="sub"', $result['html']);
    }

    public function test_enrich_returns_empty_toc_for_blank_html(): void
    {
        $this->assertSame(['html' => '', 'toc' => []], MarkdownToc::enrich('   '));
    }
}
