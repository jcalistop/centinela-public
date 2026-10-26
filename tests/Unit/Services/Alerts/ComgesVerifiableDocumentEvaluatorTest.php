<?php

namespace Tests\Unit\Services\Alerts;

use App\Enums\Comges\DocumentoVerificableType;
use App\Models\ComAction;
use App\Models\ComVerifiableDocument;
use App\Services\Alerts\ComgesVerifiableDocumentEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComgesVerifiableDocumentEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_uploaded_files_returns_false_for_empty_json(): void
    {
        $document = new ComVerifiableDocument;
        $document->archivos = '[]';

        $this->assertFalse(ComgesVerifiableDocumentEvaluator::hasUploadedFiles($document));
    }

    public function test_has_uploaded_files_returns_true_when_download_link_present(): void
    {
        $document = new ComVerifiableDocument;
        $document->archivos = json_encode([
            ['download_link' => '2025/file.pdf', 'original_name' => 'file.pdf'],
        ]);

        $this->assertTrue(ComgesVerifiableDocumentEvaluator::hasUploadedFiles($document));
    }

    public function test_has_empty_document_for_evaluacion_type(): void
    {
        $action = $this->makeActionWithDocument(
            cenHieDependencyId: 10,
            type: DocumentoVerificableType::Evaluacion,
            archivos: '[]',
        );

        $this->assertTrue(
            ComgesVerifiableDocumentEvaluator::hasEmptyDocumentForType(
                collect([$action]),
                10,
                DocumentoVerificableType::Evaluacion,
            )
        );
    }

    public function test_all_documents_loaded_for_evaluacion_when_files_present(): void
    {
        $action = $this->makeActionWithDocument(
            cenHieDependencyId: 10,
            type: DocumentoVerificableType::Evaluacion,
            archivos: json_encode([['download_link' => 'x.pdf', 'original_name' => 'x.pdf']]),
        );

        $this->assertTrue(
            ComgesVerifiableDocumentEvaluator::allDocumentsLoadedForType(
                collect([$action]),
                10,
                DocumentoVerificableType::Evaluacion,
            )
        );
    }

    private function makeActionWithDocument(
        int $cenHieDependencyId,
        DocumentoVerificableType $type,
        string $archivos,
    ): ComAction {
        $action = new ComAction;
        $document = new ComVerifiableDocument;
        $document->cen_hie_dependency_id = $cenHieDependencyId;
        $document->verifiable_type = $type;
        $document->archivos = $archivos;
        $action->setRelation('comVerifiableDocuments', collect([$document]));

        return $action;
    }
}
