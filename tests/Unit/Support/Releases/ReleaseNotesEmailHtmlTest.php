<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Releases;

use App\Support\Releases\ReleaseNotesEmailHtml;
use PHPUnit\Framework\TestCase;

class ReleaseNotesEmailHtmlTest extends TestCase
{
    /** @test */
    public function render_converts_markdown_and_strips_inline_html_tags(): void
    {
        $html = ReleaseNotesEmailHtml::render("### <u>Centinela v3.0.0</u>\r\n\r\n- **Item** uno");

        $this->assertStringNotContainsString('<u>', $html);
        $this->assertStringNotContainsString('</u>', $html);
        $this->assertStringContainsString('<h3>', $html);
        $this->assertStringContainsString('Centinela v3.0.0', $html);
        $this->assertStringContainsString('<strong>Item</strong>', $html);
    }

    /** @test */
    public function render_returns_empty_for_blank_body(): void
    {
        $this->assertSame('', ReleaseNotesEmailHtml::render('   '));
    }
}
