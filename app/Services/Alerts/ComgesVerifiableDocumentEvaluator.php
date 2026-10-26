<?php

namespace App\Services\Alerts;

use App\Enums\Comges\DocumentoVerificableType;
use App\Models\ComAction;
use App\Models\ComIndicatorCutoff;
use App\Models\ComVerifiableDocument;
use Illuminate\Support\Collection;

final class ComgesVerifiableDocumentEvaluator
{
    public static function hasUploadedFiles(ComVerifiableDocument $document): bool
    {
        $raw = $document->archivos;

        if ($raw === null || $raw === '') {
            return false;
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded) || $decoded === []) {
            return false;
        }

        foreach ($decoded as $entry) {
            if (is_object($entry)) {
                $entry = (array) $entry;
            }

            if (! is_array($entry)) {
                continue;
            }

            $link = $entry['download_link'] ?? null;

            if (is_string($link) && $link !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, ComAction>  $actions
     */
    public static function hasEmptyDocumentForType(
        Collection $actions,
        int $cenHieDependencyId,
        DocumentoVerificableType $type,
    ): bool {
        foreach ($actions as $action) {
            foreach ($action->comVerifiableDocuments as $document) {
                if ((int) $document->cen_hie_dependency_id !== $cenHieDependencyId) {
                    continue;
                }

                if ($document->verifiable_type !== $type) {
                    continue;
                }

                if (! self::hasUploadedFiles($document)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, ComAction>  $actions
     */
    public static function allDocumentsLoadedForType(
        Collection $actions,
        int $cenHieDependencyId,
        DocumentoVerificableType $type,
    ): bool {
        if ($actions->isEmpty()) {
            return false;
        }

        foreach ($actions as $action) {
            $hasLoaded = false;

            foreach ($action->comVerifiableDocuments as $document) {
                if ((int) $document->cen_hie_dependency_id !== $cenHieDependencyId) {
                    continue;
                }

                if ($document->verifiable_type !== $type) {
                    continue;
                }

                if (self::hasUploadedFiles($document)) {
                    $hasLoaded = true;

                    break;
                }
            }

            if (! $hasLoaded) {
                return false;
            }
        }

        return true;
    }

    public static function cutoffHasAnyAction(ComIndicatorCutoff $cutoff): bool
    {
        return $cutoff->comActions->isNotEmpty();
    }
}
