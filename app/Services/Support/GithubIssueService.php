<?php

namespace App\Services\Support;

use App\Models\SupportTicket;
use App\Models\User;
use App\Support\SupportTicket\SupportTicketCatalog;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GithubIssueService
{
    /**
     * @return array{number: int, html_url: string}
     *
     * @throws RequestException
     */
    public function createIssue(SupportTicket $ticket, User $admin): array
    {
        $repo = (string) config('centinela.github_issues.repo');
        $token = (string) config('centinela.github_issues.token');

        if ($repo === '' || ! str_contains($repo, '/')) {
            throw new \InvalidArgumentException('GITHUB_RELEASES_REPOSITORY debe tener forma owner/repo.');
        }

        if ($token === '') {
            throw new \InvalidArgumentException('CENTINELA_GITHUB_TOKEN no configurado (compat: RELEASES_TOKEN).');
        }

        [$owner, $name] = explode('/', $repo, 2);
        $owner = trim($owner);
        $name = trim($name);

        $ticketUrl = route('centinela.support.show', [$ticket], true);
        $body = $this->buildIssueBody($ticket, $ticketUrl);

        $response = Http::withToken($token)
            ->acceptJson()
            ->post("https://api.github.com/repos/{$owner}/{$name}/issues", [
                'title' => '[Centinela-Soporte #'.$ticket->id.'] '.$ticket->subject,
                'body' => $body,
            ])
            ->throw();

        /** @var array{number: int, html_url: string} $data */
        $data = $response->json();

        return [
            'number' => (int) $data['number'],
            'html_url' => (string) $data['html_url'],
        ];
    }

    private function buildIssueBody(SupportTicket $ticket, string $ticketUrl): string
    {
        $areaLabel = SupportTicketCatalog::labelForArea($ticket->area);
        $ssId = (string) $ticket->cen_hie_dependency_id;
        $excerpt = Str::limit(strip_tags($ticket->body), 800);
        $attachmentName = is_array($ticket->attachment) ? ($ticket->attachment['name'] ?? null) : null;

        $lines = [
            '**Resumen**',
            $excerpt,
            '',
            '**Metadatos**',
            '- Área: '.$areaLabel,
            '- Servicio de Salud (id): '.$ssId,
        ];

        if (is_string($attachmentName) && $attachmentName !== '') {
            $lines[] = '- Archivo adjunto: '.$attachmentName;
        }

        $lines[] = '';
        $lines[] = '**Enlace al ticket en Centinela**';
        $lines[] = $ticketUrl;

        return implode("\n", $lines);
    }
}
