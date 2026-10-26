<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;

/**
 * Inserta ids en encabezados del HTML generado desde Markdown y construye una tabla de contenidos.
 */
final class MarkdownToc
{
    /**
     * @return array{html: string, toc: list<array{level: int, text: string, id: string}>}
     */
    public static function enrich(string $html): array
    {
        $html = trim($html);
        if ($html === '') {
            return ['html' => $html, 'toc' => []];
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previousLibxml = libxml_use_internal_errors(true);
        $payload = '<?xml encoding="UTF-8"><div id="markdown-toc-root">'.$html.'</div>';
        $doc->loadHTML($payload, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxml);

        $root = $doc->getElementById('markdown-toc-root');
        if (! $root instanceof DOMElement) {
            return ['html' => $html, 'toc' => []];
        }

        $xpath = new DOMXPath($doc);
        $headings = $xpath->query('.//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6]', $root);

        $usedIds = [];
        $toc = [];

        foreach ($headings as $heading) {
            if (! $heading instanceof DOMElement) {
                continue;
            }

            $text = self::headingText($heading);
            $level = (int) substr($heading->nodeName, 1);

            $baseSlug = Str::slug($text);
            if ($baseSlug === '') {
                $baseSlug = 'seccion';
            }

            $id = self::uniqueId($baseSlug, $usedIds);
            $heading->setAttribute('id', $id);
            $usedIds[$id] = true;

            $toc[] = [
                'level' => $level,
                'text' => $text,
                'id' => $id,
            ];
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return ['html' => $out, 'toc' => $toc];
    }

    private static function headingText(DOMElement $heading): string
    {
        $raw = $heading->textContent ?? '';

        return trim(preg_replace('/\s+/u', ' ', $raw) ?? '');
    }

    /**
     * @param  array<string, true>  $usedIds
     */
    private static function uniqueId(string $base, array &$usedIds): string
    {
        $candidate = $base;
        $i = 2;
        while (isset($usedIds[$candidate])) {
            $candidate = $base.'-'.$i;
            $i++;
        }

        return $candidate;
    }
}
