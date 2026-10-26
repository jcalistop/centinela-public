<div>
    @if (!$isOpen)
        <button type="button" class="chat-ia-launcher" wire:click="open" aria-label="Abrir Chat IA">
            Chat IA
        </button>
    @endif

    <div class="chat-ia-backdrop @if ($isOpen) is-open @endif" wire:click="close" aria-hidden="true">
    </div>

    <div class="chat-ia-drawer @if ($isOpen) is-open @endif"
        aria-hidden="{{ $isOpen ? 'false' : 'true' }}">
        <div class="chat-ia-drawer__header">
            <div class="chat-ia-drawer__title">
                Chat IA
                <span class="chat-ia-drawer__subtitle">
                    {{ $pageContext['route_name'] ?? '—' }}
                </span>
            </div>
            <div class="chat-ia-drawer__actions">
                <button type="button" class="chat-ia-drawer__close" wire:click="close" aria-label="Cerrar">×</button>
            </div>
        </div>

        <div class="chat-ia-drawer__body">
            <div class="chat-ia-disclaimer">
                Respuesta asistida. No sustituye normativa ni criterio institucional.
            </div>

            @if (config('app.debug'))
                @php
                    $indicatorYear = session('indicator_year');
                    $indicatorYearId =
                        $indicatorYear instanceof \App\Models\Year
                            ? $indicatorYear->getKey()
                            : (is_array($indicatorYear) &&
                            isset($indicatorYear['id']) &&
                            is_numeric($indicatorYear['id'])
                                ? (int) $indicatorYear['id']
                                : null);
                    $u = auth()->user();
                    $cenRaw = $u instanceof \App\Models\User ? $u->getRawOriginal('cen_hie_dependency_id') : null;
                    $cenAccessor = $u instanceof \App\Models\User ? $u->cen_hie_dependency_id : null;
                @endphp
                <details class="chat-ia-debug" open>
                    <summary class="chat-ia-debug__summary">Contexto (APP_DEBUG): cen_hie en PageContext =
                        <code>{{ $pageContext['cen_hie_dependency_id'] ?? 'null' }}</code>
                    </summary>
                    <dl class="chat-ia-debug__dl">
                        <dt><code>pageContext[indicator_type_id]</code></dt>
                        <dd><code>{{ $pageContext['indicator_type_id'] ?? 'null' }}</code></dd>
                        <dt><code>pageContext[year_id]</code></dt>
                        <dd><code>{{ $pageContext['year_id'] ?? 'null' }}</code></dd>
                        <dt><code>pageContext[cen_hie_dependency_id]</code></dt>
                        <dd><code>{{ $pageContext['cen_hie_dependency_id'] ?? 'null' }}</code></dd>
                        <dt><code>session('indicator_year')</code> → id</dt>
                        <dd><code>{{ $indicatorYearId ?? 'null' }}</code></dd>
                        <dt>Usuario: rol</dt>
                        <dd><code>{{ $u?->role?->name ?? 'null' }}</code></dd>
                        <dt><code>User::getRawOriginal('cen_hie_dependency_id')</code> (BD)</dt>
                        <dd><code>{{ $cenRaw ?? 'null' }}</code></dd>
                        <dt><code>User::cen_hie_dependency_id</code> (accessor)</dt>
                        <dd><code>{{ $cenAccessor ?? 'null' }}</code></dd>
                    </dl>
                    <p class="chat-ia-debug__hint">Tras enviar un mensaje, el contexto se re-sincroniza. Con APP_DEBUG,
                        la respuesta del asistente incluye al final el mismo resumen de contexto.</p>
                </details>
            @endif

            <div class="chat-ia-messages">
                @forelse ($messages as $msg)
                    <div class="chat-ia-message chat-ia-message--{{ $msg['role'] }}">
                        <div class="chat-ia-message__role">{{ $msg['role'] === 'user' ? 'Tú' : 'Asistente' }}</div>
                        <div class="chat-ia-message__content">{{ $msg['content'] }}</div>
                    </div>
                @empty
                    <div class="chat-ia-empty">
                        Haz una pregunta sobre el instrumento, indicadores o documentación de esta pantalla.
                    </div>
                @endforelse
            </div>
        </div>

        <form class="chat-ia-drawer__footer" wire:submit.prevent="send">
            <input type="text" class="chat-ia-input" wire:model.defer="input" placeholder="Escribe tu pregunta…" />
            <button type="submit" class="chat-ia-send">Enviar</button>
        </form>
    </div>

    <style>
        .chat-ia-launcher {
            position: fixed;
            bottom: 3rem;
            right: 3rem;
            z-index: 1060;
            border: 0;
            border-radius: 999px;
            padding: 10px 14px;
            background: #22A7F0;
            color: #fff;
            font-weight: 600;
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.18);
        }

        .chat-ia-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.35);
            z-index: 1061;
        }

        .chat-ia-backdrop.is-open {
            display: block;
        }

        .chat-ia-drawer {
            position: fixed;
            top: 0;
            right: 0;
            height: 100%;
            width: min(560px, 92vw);
            background: #fff;
            border-left: 1px solid #e5e5e5;
            z-index: 1062;
            transform: translateX(100%);
            transition: transform 150ms ease-out;
            display: flex;
            flex-direction: column;
        }

        .chat-ia-drawer.is-open {
            transform: translateX(0);
        }

        .chat-ia-drawer__header {
            padding: 12px 14px;
            border-bottom: 1px solid #e5e5e5;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .chat-ia-drawer__title {
            font-weight: 600;
            font-size: 14px;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .chat-ia-drawer__subtitle {
            font-size: 11px;
            color: #666;
            font-weight: 400;
        }

        .chat-ia-drawer__close {
            background: transparent;
            border: 0;
            font-size: 22px;
            line-height: 1;
            padding: 0 6px;
        }

        .chat-ia-drawer__body {
            padding: 14px;
            overflow: auto;
            flex: 1;
        }

        .chat-ia-disclaimer {
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
        }

        .chat-ia-debug {
            font-size: 12px;
            line-height: 1.35;
            color: #333;
            background: #f4f4f4;
            border: 1px dashed #bbb;
            border-radius: 8px;
            padding: 8px 10px;
            margin-bottom: 12px;
        }

        .chat-ia-debug__summary {
            cursor: pointer;
            font-weight: 600;
        }

        .chat-ia-debug__summary code {
            font-weight: 400;
        }

        .chat-ia-debug__dl {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(0, 0.9fr);
            gap: 4px 10px;
            margin: 8px 0 0;
        }

        .chat-ia-debug__dl dt {
            color: #555;
            word-break: break-word;
        }

        .chat-ia-debug__dl dd {
            margin: 0;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 11px;
        }

        .chat-ia-debug__hint {
            margin: 8px 0 0;
            font-size: 11px;
            color: #666;
        }
    </style>
</div>
