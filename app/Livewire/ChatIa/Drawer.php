<?php

namespace App\Livewire\ChatIa;

use App\Models\User;
use App\Models\Year;
use App\Support\ChatIa\DbReadOnlyResponder;
use App\Support\ChatIa\PageContextResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Component;

class Drawer extends Component
{
    private const int MAX_STORED_MESSAGES = 80;

    /**
     * @var array<int, array{role: 'user'|'assistant', content: string}>
     */
    public array $messages = [];

    public string $input = '';

    public bool $isOpen = false;

    /** Path HTTP de la página donde se montó el chat (evita `/livewire/update` en subsecuentes). */
    public string $browserPath = '';

    /** Nombre de ruta Laravel de esa página (evita `livewire.update`). */
    public string $browserRouteName = '';

    /**
     * @var array{
     *   route_name: string,
     *   path: string,
     *   indicator_type_id: int|null,
     *   year_id: int|null,
     *   cen_hie_dependency_id: int|null,
     *   extra: array<string, mixed>
     * }|null
     */
    public ?array $pageContext = null;

    public function mount(Request $request): void
    {
        if (! config('centinela.chat_ia.enabled')) {
            return;
        }

        $this->captureBrowserContext($request);
        $this->pageContext = PageContextResolver::fromRequest($request, $this->browserPath, $this->browserRouteName)->toArray();
        $this->messages = $this->loadMessagesFromSession();
    }

    public function open(): void
    {
        if (! config('centinela.chat_ia.enabled')) {
            return;
        }

        $this->pageContext = PageContextResolver::fromRequest(request(), $this->browserPath, $this->browserRouteName)->toArray();
        $this->messages = $this->loadMessagesFromSession();
        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    public function send(): void
    {
        if (! config('centinela.chat_ia.enabled')) {
            return;
        }

        $text = trim($this->input);
        if ($text === '') {
            return;
        }

        $this->pageContext = PageContextResolver::fromRequest(request(), $this->browserPath, $this->browserRouteName)->toArray();
        $this->messages = $this->loadMessagesFromSession();

        $this->messages[] = [
            'role' => 'user',
            'content' => $text,
        ];

        $this->input = '';

        /** @var DbReadOnlyResponder $responder */
        $responder = app(DbReadOnlyResponder::class);

        $assistantContent = $responder->respond($this->pageContext ?? [], $text);

        $this->pageContext = PageContextResolver::fromRequest(request(), $this->browserPath, $this->browserRouteName)->toArray();

        if (config('app.debug')) {
            $assistantContent .= "\n\n".$this->formatAssistantContextFooter($this->pageContext ?? []);
        }

        $this->messages[] = [
            'role' => 'assistant',
            'content' => $assistantContent,
        ];

        $this->persistMessagesToSession();
    }

    public function render()
    {
        if (! config('centinela.chat_ia.enabled')) {
            return view('livewire.chat-ia.empty');
        }

        return view('livewire.chat-ia.drawer');
    }

    /**
     * @return array<int, array{role: 'user'|'assistant', content: string}>
     */
    private function loadMessagesFromSession(): array
    {
        $raw = Session::get($this->threadSessionKey());

        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $role = $row['role'] ?? null;
            $content = $row['content'] ?? null;
            if (($role === 'user' || $role === 'assistant') && is_string($content) && $content !== '') {
                $out[] = ['role' => $role, 'content' => $content];
            }
        }

        return array_slice($out, -self::MAX_STORED_MESSAGES);
    }

    private function persistMessagesToSession(): void
    {
        Session::put(
            $this->threadSessionKey(),
            array_slice($this->messages, -self::MAX_STORED_MESSAGES)
        );
    }

    private function captureBrowserContext(Request $request): void
    {
        $this->browserPath = '/'.ltrim($request->path(), '/');
        $this->browserRouteName = (string) ($request->route()?->getName() ?? 'unknown');
    }

    /**
     * Texto anexado a la respuesta del asistente cuando APP_DEBUG está activo.
     *
     * @param  array<string, mixed>  $pageContext
     */
    private function formatAssistantContextFooter(array $pageContext): string
    {
        $indicatorYear = Session::get('indicator_year');
        $indicatorYearSessionId = null;
        if ($indicatorYear instanceof Year) {
            $indicatorYearSessionId = (int) $indicatorYear->getKey();
        } elseif (is_array($indicatorYear) && isset($indicatorYear['id']) && is_numeric($indicatorYear['id'])) {
            $indicatorYearSessionId = (int) $indicatorYear['id'];
        }

        $user = Auth::user();
        $cenRaw = $user instanceof User ? $user->getRawOriginal('cen_hie_dependency_id') : null;
        $cenAccessor = $user instanceof User ? $user->cen_hie_dependency_id : null;

        $lines = [
            '---',
            'Contexto actualizado (tras este mensaje):',
            '- browserRouteName (snapshot Livewire): '.$this->browserRouteName,
            '- browserPath (snapshot Livewire): '.$this->browserPath,
            '- route_name: '.($pageContext['route_name'] ?? '—'),
            '- path: '.($pageContext['path'] ?? '—'),
            '- indicator_type_id: '.($pageContext['indicator_type_id'] ?? 'null'),
            '- year_id (PageContext): '.($pageContext['year_id'] ?? 'null'),
            '- cen_hie_dependency_id (PageContext): '.($pageContext['cen_hie_dependency_id'] ?? 'null'),
            '- session(indicator_year) id: '.($indicatorYearSessionId ?? 'null'),
            '- usuario rol: '.($user?->role?->name ?? 'null'),
            '- User cen_hie raw (BD): '.($cenRaw ?? 'null'),
            '- User cen_hie (accessor): '.($cenAccessor ?? 'null'),
        ];

        $extra = $pageContext['extra'] ?? null;
        if (is_array($extra) && $extra !== []) {
            $lines[] = '- extra: '.json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return implode("\n", $lines);
    }

    private function threadSessionKey(): string
    {
        $user = Auth::user();
        $userPart = $user instanceof User ? (string) $user->getKey() : 'guest';

        $c = $this->pageContext ?? [];
        $payload = json_encode([
            'indicator_type_id' => $c['indicator_type_id'] ?? null,
            'year_id' => $c['year_id'] ?? null,
            'cen_hie_dependency_id' => $c['cen_hie_dependency_id'] ?? null,
        ]) ?: '';

        $fingerprint = hash('sha256', $payload);

        return 'chat_ia_thread_'.$userPart.'_'.$fingerprint;
    }
}
