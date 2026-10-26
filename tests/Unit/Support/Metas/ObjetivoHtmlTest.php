<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Metas;

use App\Support\Metas\ObjetivoHtml;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ObjetivoHtmlTest extends TestCase
{
    #[Test]
    public function sanitize_preserva_allowlist(): void
    {
        $html = '<p><strong>x</strong> <u>y</u></p>';

        $this->assertSame($html, ObjetivoHtml::sanitize($html));
    }

    #[Test]
    public function sanitize_elimina_tags_y_attrs_peligrosos(): void
    {
        $dirty = '<p>ok</p><script>alert(1)</script><iframe src="x"></iframe>'
            .'<img onerror="alert(1)" src="x"><div style="color:red">z</div>'
            .'<a href="https://evil.test">link</a>';

        $clean = ObjetivoHtml::sanitize($dirty);

        $this->assertNotNull($clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('<iframe', $clean);
        $this->assertStringNotContainsString('<img', $clean);
        $this->assertStringNotContainsString('<div', $clean);
        $this->assertStringNotContainsString('<a ', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringContainsString('ok', $clean);
        $this->assertStringContainsString('z', $clean);
        $this->assertStringContainsString('link', $clean);
    }

    #[Test]
    public function is_plain_text_detecta_legacy_y_html(): void
    {
        $this->assertTrue(ObjetivoHtml::isPlainText("a\nb"));
        $this->assertFalse(ObjetivoHtml::isPlainText('<p>a</p>'));
    }

    #[Test]
    public function to_safe_html_plain_usa_nl2br_escapado(): void
    {
        $html = ObjetivoHtml::toSafeHtml("a\nb");

        $this->assertStringContainsString('<br', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringContainsString('a', $html);
    }

    #[Test]
    public function to_plain_excerpt_trunca_html_sin_tags(): void
    {
        $stored = '<p>'.str_repeat('A', 300).'</p>';
        $excerpt = ObjetivoHtml::toPlainExcerpt($stored, 220);

        $this->assertNotNull($excerpt);
        $this->assertLessThanOrEqual(220, mb_strlen($excerpt));
        $this->assertStringNotContainsString('<', $excerpt);
        $this->assertStringEndsWith('…', $excerpt);
    }

    #[Test]
    public function sanitize_vacio_tras_strip_devuelve_null(): void
    {
        $this->assertNull(ObjetivoHtml::sanitize('<script></script>'));
        $this->assertNull(ObjetivoHtml::sanitize('<p><br></p>'));
        $this->assertNull(ObjetivoHtml::sanitize(''));
        $this->assertNull(ObjetivoHtml::sanitize(null));
    }
}
