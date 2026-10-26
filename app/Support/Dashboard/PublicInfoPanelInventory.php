<?php

namespace App\Support\Dashboard;

use App\Models\PublicInfoPanel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Inventario de solo lectura de paneles públicos (SDD-302).
 *
 * Entrega metadatos mínimos para copiar al portapapeles; no genera descargas.
 */
final class PublicInfoPanelInventory
{
    public const SIN_PANELES = 'Sin paneles registrados';

    /**
     * Paneles del catálogo completo, en el orden del listado.
     *
     * @return Collection<int, PublicInfoPanel>
     */
    public static function panels(): Collection
    {
        return PublicInfoPanel::query()
            ->orderBy('orden')
            ->orderBy('titulo')
            ->get();
    }

    /**
     * @param  iterable<int, PublicInfoPanel>  $panels
     * @return array<int, array{slug: string, titulo: string, tipo: string, publicado: bool}>
     */
    public static function rows(iterable $panels): array
    {
        $rows = [];

        foreach ($panels as $panel) {
            $rows[] = [
                'slug' => (string) $panel->slug,
                'titulo' => (string) $panel->titulo,
                'tipo' => (string) ($panel->tipo ?: PublicInfoPanel::TIPO_HTML_BUNDLE),
                'publicado' => (bool) $panel->publicado,
            ];
        }

        return $rows;
    }

    /**
     * @param  iterable<int, PublicInfoPanel>  $panels
     */
    public static function markdown(iterable $panels): string
    {
        $rows = self::rows($panels);
        $lines = [
            '# Inventario de paneles públicos (Centinela)',
            '',
            sprintf('Generado: %s · Total: %d', self::generatedAt(), count($rows)),
            '',
        ];

        if ($rows === []) {
            $lines[] = self::SIN_PANELES;

            return implode("\n", $lines);
        }

        $lines[] = '| Slug | Título | Tipo | Publicado |';
        $lines[] = '| --- | --- | --- | --- |';

        foreach ($rows as $row) {
            $lines[] = sprintf(
                '| %s | %s | %s | %s |',
                self::escapeCell($row['slug']),
                self::escapeCell($row['titulo']),
                self::tipoLabel($row['tipo']),
                $row['publicado'] ? 'Sí' : 'No',
            );
        }

        return implode("\n", $lines);
    }

    /**
     * @param  iterable<int, PublicInfoPanel>  $panels
     */
    public static function toJson(iterable $panels): string
    {
        $rows = self::rows($panels);

        return (string) json_encode([
            'generado' => self::generatedAt(),
            'total' => count($rows),
            'paneles' => $rows,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function generatedAt(): string
    {
        return Carbon::now()->format('Y-m-d H:i');
    }

    private static function tipoLabel(string $tipo): string
    {
        return $tipo === PublicInfoPanel::TIPO_EMBED_EXTERNO ? 'Embed' : 'HTML';
    }

    private static function escapeCell(string $value): string
    {
        return str_replace('|', '\|', $value);
    }
}
