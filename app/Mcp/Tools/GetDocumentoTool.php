<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithAgentFacade;
use App\Services\Agent\DocumentsReadAdapter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

#[IsReadOnly]
class GetDocumentoTool extends Tool
{
    use InteractsWithAgentFacade;

    protected string $description = 'Metadatos de un documento normativo del SS del token más URL firmada temporal de descarga. Solo lectura.';

    public function handle(Request $request, DocumentsReadAdapter $documents): Response
    {
        $user = $this->requireAgentUser();
        if ($user instanceof Response) {
            return $user;
        }

        $ssId = (int) $user->cen_hie_dependency_id;
        $rawId = $request->get('id');
        if (! is_numeric($rawId) || (int) $rawId < 1) {
            return $this->jsonError('VALIDATION_ERROR', 'id no es válido.', ['id' => $rawId]);
        }

        $id = (int) $rawId;
        $document = $documents->findInSs($id, $ssId);
        if ($document === null) {
            return $this->jsonError('NOT_FOUND', 'Documento no encontrado.', ['id' => $id]);
        }

        $entry = $documents->primaryFileEntry($document);
        if ($entry === null) {
            return $this->jsonError('NOT_FOUND', 'Documento no encontrado.', ['id' => $id]);
        }

        $ttl = $documents->ttlMinutes();
        $expiresAt = now()->addMinutes($ttl);

        try {
            $downloadUrl = URL::temporarySignedRoute(
                'centinela.documents.download',
                $expiresAt,
                [
                    'document' => $document->id,
                    'ss' => $ssId,
                    'file_index' => $entry['file_index'],
                ]
            );
        } catch (Throwable $e) {
            Log::error('get-documento-tool failed signing URL', [
                'id' => $id,
                'request_id' => request()->attributes->get('agent_request_id'),
                'exception' => $e->getMessage(),
            ]);

            return $this->jsonError('INTERNAL_ERROR', 'No se pudo firmar la descarga.', ['id' => $id]);
        }

        $payload = $documents->mapDocument($document);
        $payload['download_url'] = $downloadUrl;
        $payload['expires_at'] = $expiresAt->toIso8601String();

        return $this->jsonOk($payload);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('ID del documento.')->required(),
        ];
    }
}
