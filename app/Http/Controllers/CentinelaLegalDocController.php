<?php

namespace App\Http\Controllers;

use App\Support\MarkdownMermaid;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CentinelaLegalDocController extends Controller
{
    /**
     * @var array<string, array{file: string, page_title: string}>
     */
    private const DOCS = [
        'terminos-de-uso' => [
            'file' => 'terminos-de-uso.md',
            'page_title' => 'Términos de uso',
        ],
        'politica-de-privacidad' => [
            'file' => 'politica-de-privacidad.md',
            'page_title' => 'Política de privacidad',
        ],
    ];

    public function terminos(): View
    {
        $this->abortUnlessLoginLegalDocumentsEnabled();

        return $this->renderDoc('terminos-de-uso');
    }

    public function privacidad(): View
    {
        $this->abortUnlessLoginLegalDocumentsEnabled();

        return $this->renderDoc('politica-de-privacidad');
    }

    private function abortUnlessLoginLegalDocumentsEnabled(): void
    {
        if (! (bool) config('centinela.login_legal_documents.enabled')) {
            abort(404);
        }
    }

    private function renderDoc(string $slug): View
    {
        if (! (bool) config('centinela.login_legal_documents.enabled', false)) {
            throw new NotFoundHttpException('Documento legal no disponible.');
        }

        $meta = self::DOCS[$slug];
        $path = resource_path('docs/centinela/'.$meta['file']);

        if (! is_readable($path)) {
            throw new NotFoundHttpException('Documento legal no disponible.');
        }

        $markdown = (string) file_get_contents($path);
        $markdownHtml = MarkdownMermaid::toHtml($markdown);

        return view('centinela.legal.markdown', [
            'pageTitle' => $meta['page_title'],
            'markdownHtml' => $markdownHtml,
        ]);
    }
}
