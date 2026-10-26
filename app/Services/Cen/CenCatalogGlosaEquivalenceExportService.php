<?php

namespace App\Services\Cen;

use App\Support\Cen\CenCatalogGlosaResolver;
use App\Support\Cen\CenEstablishmentsUiCopy;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class CenCatalogGlosaEquivalenceExportService
{
    public function __construct(
        private readonly CenCatalogGlosaEquivalenceService $equivalenceService,
    ) {}

    public function downloadActive(): BinaryFileResponse
    {
        $fileName = date('YmdHi').'_equivalencias-catalogo.xlsx';
        $tempPath = storage_path('temp/'.$fileName);

        if (! is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        $headers = [
            'Campo',
            'Clave campo',
            'Texto catálogo nacional',
            'Valor Centinela',
            'Tipo de regla',
            'Revisado por',
            'Actualizado',
        ];

        $writer = SimpleExcelWriter::create($tempPath);
        $writer->addHeader($headers);

        $fieldLabels = CenCatalogGlosaResolver::FIELD_LABELS;

        foreach ($this->equivalenceService->activeOrderedForExport() as $equivalence) {
            $writer->addRow([
                $fieldLabels[$equivalence->field_key] ?? $equivalence->field_key,
                $equivalence->field_key,
                $equivalence->source_glosa,
                $equivalence->target_value,
                CenEstablishmentsUiCopy::ruleKindLabel($equivalence->rule_kind->value),
                $equivalence->reviewedBy?->name ?? '',
                $equivalence->updated_at?->format('Y-m-d H:i:s') ?? '',
            ]);
        }

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }
}
