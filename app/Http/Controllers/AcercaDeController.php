<?php

namespace App\Http\Controllers;

use App\Support\AcercaDePages;
use App\Support\MarkdownMermaid;
use App\Support\MarkdownToc;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AcercaDeController extends Controller
{
    public function show(string $slug = 'resumen'): View
    {
        if (! array_key_exists($slug, AcercaDePages::PAGES)) {
            throw new NotFoundHttpException('Página no encontrada.');
        }

        $page = AcercaDePages::PAGES[$slug];
        $path = resource_path('docs/centinela/acerca-de/'.$page['file']);

        if (! is_readable($path)) {
            throw new NotFoundHttpException('Documento no disponible.');
        }

        $markdown = (string) file_get_contents($path);
        $markdownHtml = MarkdownMermaid::toHtml($markdown);
        $enriched = MarkdownToc::enrich($markdownHtml);

        return view('centinela.acerca-de.page', [
            'markdownHtml' => $enriched['html'],
            'pageTitle' => $page['title'],
            'pageIcon' => $page['icon'],
            'currentSlug' => $slug,
            'navPages' => AcercaDePages::navigation(),
        ]);
    }
}
