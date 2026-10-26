<?php

namespace App\Support\Dashboard;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Contrato .md versionado en repo para generadores externos de paneles (SDD-283 / SDD-291).
 */
final class PublicInfoPanelAgentGuide
{
    public static function absolutePath(): string
    {
        return resource_path('public-info-panels/contrato-agentes-paneles.md');
    }

    public static function downloadFilename(): string
    {
        return 'contrato-agentes-paneles.md';
    }

    public static function contents(): string
    {
        $path = self::absolutePath();
        if (! is_file($path)) {
            return '';
        }

        $contents = file_get_contents($path);

        return is_string($contents) ? $contents : '';
    }

    /**
     * StreamedResponse para que Livewire/Filament disparen la descarga en acciones de modal.
     */
    public static function downloadResponse(): StreamedResponse
    {
        $contents = self::contents();
        if ($contents === '') {
            abort(404);
        }

        return response()->streamDownload(
            function () use ($contents): void {
                echo $contents;
            },
            self::downloadFilename(),
            ['Content-Type' => 'text/markdown; charset=UTF-8']
        );
    }
}
