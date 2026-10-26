<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Convierte Markdown a HTML y transforma bloques de código `language-mermaid`
 * (salida típica de CommonMark) en `<pre class="mermaid">` para render con Mermaid.js.
 *
 * También reescribe `src`/`href` root-relative (`/docs/...`, `/admin/...`) para
 * respetar el subdirectorio de `APP_URL` (p. ej. `/centinela` en intranet IIS).
 */
final class MarkdownMermaid
{
    public static function toHtml(string $markdown): string
    {
        $html = Str::markdown($markdown);

        $html = preg_replace_callback(
            '#<pre><code class="language-mermaid">(.*?)</code></pre>#s',
            static function (array $matches): string {
                $inner = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

                return '<pre class="mermaid">'.$inner.'</pre>';
            },
            $html
        ) ?? $html;

        return self::rewriteAppRelativeUrls($html);
    }

    /**
     * Prefija rutas absolutas desde la raíz del host con el path de APP_URL.
     *
     * Ejemplo: APP_URL=…/centinela y src="/docs/diagrams/x.svg"
     * → src="/centinela/docs/diagrams/x.svg"
     */
    public static function rewriteAppRelativeUrls(string $html): string
    {
        $basePath = rtrim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');

        if ($basePath === '' || $basePath === '/') {
            return $html;
        }

        return preg_replace_callback(
            '#\b(src|href)="(/(?!/)[^"]*)"#',
            static function (array $matches) use ($basePath): string {
                $path = $matches[2];

                if ($path === $basePath || str_starts_with($path, $basePath.'/')) {
                    return $matches[0];
                }

                return $matches[1].'="'.$basePath.$path.'"';
            },
            $html
        ) ?? $html;
    }
}
