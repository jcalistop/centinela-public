@extends('voyager::master')

@php
    $isAdmin = auth()->user()->hasRole('admin');
    $currentUserId = auth()->id();

    // Paleta fija para avatares (6 colores)
    $avatarPalette = ['#3498db', '#e74c3c', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c'];

    function avatarColor($userId, array $palette): string
    {
        return $palette[$userId % count($palette)];
    }

    function avatarInitials(?string $name): string
    {
        if (!$name) {
            return '?';
        }
        $parts = explode(' ', trim($name));
        if (count($parts) >= 2) {
            return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
        }

        return mb_strtoupper(mb_substr($name, 0, 2));
    }
@endphp

@section('css')
    @include('centinela.support.partials.compass-shell')
    <style>
        .support-avatar--{{ $currentUserId }} {
            background: {{ avatarColor($currentUserId, $avatarPalette) }};
        }
    </style>
@stop

@section('page_title', 'Soporte — Ticket #' . $ticket->id)

@section('page_header')
    <h1 class="page-title">
        <i class="voyager-file-text"></i> Ticket #{{ $ticket->id }}
    </h1>
@stop

@section('content')
    <div class="page-content compass container-fluid support-compass cen-compass-ui support-module">
        @include('voyager::alerts')

        @if (session('flash_support_success'))
            <div class="alert alert-success">{{ session('flash_support_success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="m-b-0">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <p class="m-b-15">
            @include('centinela.support.partials.back-link', [
                'href' => route('centinela.support.index'),
                'label' => 'Bandeja Soporte',
            ])
        </p>

        {{-- Panel: Informacion del ticket --}}
        <x-centinela.support.compass-block title="Informacion del ticket" icon="voyager-file-text"
            class="m-b-20 support-ticket-summary">
            <div class="support-ticket-header m-b-15">
                <span class="label label-default support-status-badge support-status-badge--{{ $ticket->status->value }}">
                    {{ $ticket->status->label() }}
                </span>
                <span class="text-muted small">
                    &middot; Creado
                    <span class="support-relative-time" title="{{ $ticket->created_at?->format('d-m-Y H:i') }}">
                        {{ $ticket->created_at?->diffForHumans() ?? '—' }}
                    </span>
                </span>
            </div>

            <h2 class="support-ticket-subject h3 m-t-0 m-b-15">{{ $ticket->subject }}</h2>

            <div class="form-group m-b-20">
                <span class="control-label">Descripcion</span>
                <div class="support-ticket-description well well-sm m-t-5 m-b-0">
                    {!! nl2br(e($ticket->body)) !!}
                </div>
            </div>

            <div class="row support-form-row-2col">
                <div class="col-md-6">
                    <dl class="support-detail-dl m-b-0">
                        <dt>Area</dt>
                        <dd>{{ \App\Support\SupportTicket\SupportTicketCatalog::labelForArea($ticket->area) }}</dd>
                        <dt>Servicio de Salud</dt>
                        <dd>{{ $ticket->cenHieDependency?->nombre ?? '—' }}</dd>
                        <dt>Creado por</dt>
                        <dd>
                            @if ($ticket->createdBy)
                                {{ $ticket->createdBy->name }} ({{ $ticket->createdBy->email ?? '—' }})
                            @else
                                —
                            @endif
                        </dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl class="support-detail-dl m-b-0">
                        <dt>Año / Instrumento</dt>
                        <dd>{{ $ticket->year?->ano ?? '—' }} / {{ $ticket->indicatorType?->nombre_indicador ?? '—' }}
                        </dd>
                        <dt>Archivo adjunto</dt>
                        <dd>
                            @if (is_array($ticket->attachment) && !empty($ticket->attachment['name']))
                                <a href="{{ route('centinela.support.attachment.download', $ticket) }}">
                                    <i class="voyager-download" aria-hidden="true"></i>
                                    {{ $ticket->attachment['name'] }}
                                </a>
                            @else
                                —
                            @endif
                        </dd>
                        <dt>Asignado a</dt>
                        <dd>
                            @if ($ticket->assignedTo)
                                {{ $ticket->assignedTo->name }} ({{ $ticket->assignedTo->email ?? '—' }})
                            @else
                                No asignado
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>

            @if ($ticket->github_issue_url)
                <hr class="m-t-20 m-b-15">
                <p id="github-issue" class="m-b-0">
                    <strong>Ticket elevado a Issue en GitHub:</strong>
                    <a class="btn btn-sm btn-link p-l-0" href="{{ $ticket->github_issue_url }}" target="_blank"
                        rel="noopener noreferrer">
                        <i class="voyager-git" aria-hidden="true"></i> Issue #{{ $ticket->github_issue_number }}
                        <span aria-hidden="true">&nearr;</span>
                    </a>
                    @if ($ticket->github_linked_at)
                        <span class="support-relative-time text-muted small"
                            title="{{ $ticket->github_linked_at?->format('d-m-Y H:i') }}">
                            &middot; {{ $ticket->github_linked_at?->diffForHumans() }}
                        </span>
                    @endif
                </p>
            @endif

            @if ($showGithubPromote)
                <div class="clearfix m-t-20">
                    <form method="post" action="{{ route('centinela.support.github.store', $ticket) }}" class="pull-right"
                        onsubmit="return confirm('Crear un issue en GitHub?');">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            <i class="voyager-git" aria-hidden="true"></i> Crear issue en GitHub
                        </button>
                    </form>
                </div>
            @endif
        </x-centinela.support.compass-block>

        @php
            $dualStatusAssignPanels = auth()->user()->can('manage', $ticket) && auth()->user()->can('assign', $ticket);
            $supportActionColClass = $dualStatusAssignPanels ? 'col-md-6' : 'col-md-12';
        @endphp

        @if (auth()->user()->can('manage', $ticket) || auth()->user()->can('assign', $ticket))
            {{-- Estado y Asignacion: una sola fila, dos columnas (sin accordion). --}}
            <div class="row support-status-assign-row m-b-20">
                @can('manage', $ticket)
                    <div class="{{ $supportActionColClass }} m-b-20">
                        <x-centinela.support.compass-block title="Estado" icon="voyager-check" class="m-b-0">
                            <form method="post" action="{{ route('centinela.support.status.update', $ticket) }}"
                                class="form-horizontal">
                                @csrf
                                @method('PATCH')
                                @if (!$isAdmin)
                                    <input type="hidden" name="is_internal" value="0">
                                @endif
                                <div class="form-group m-b-10">
                                    <label class="control-label" for="status_select">Estado a cambiar</label>
                                    <select name="status" id="status_select" class="form-control"
                                        aria-label="Estado a cambiar">
                                        @foreach (\App\Enums\SupportTicketStatus::cases() as $st)
                                            <option value="{{ $st->value }}" @selected($ticket->status === $st)>
                                                {{ $st->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                @if ($isAdmin)
                                    <div class="form-group m-b-10">
                                        <div class="row support-comment-vis-row">
                                            <div class="col-sm-8 col-xs-12 support-comment-vis-col-comment">
                                                <label class="control-label" for="status_comment">Comentario
                                                    (opcional)</label>
                                                <textarea name="comment" id="status_comment" class="form-control" rows="3"
                                                    placeholder="Agrega contexto para el cambio…"></textarea>
                                            </div>
                                            <div class="col-sm-4 col-xs-12">
                                                <label class="control-label" for="status_is_internal">Visibilidad</label>
                                                <div class="support-visibilidad-toggle-wrap">
                                                    <input type="checkbox" name="is_internal" id="status_is_internal"
                                                        value="1" class="toggleswitch support-visibilidad-toggle"
                                                        data-on="Interno" data-off="Publico" data-onstyle="warning"
                                                        data-offstyle="default" @checked(filter_var(old('is_internal', false), FILTER_VALIDATE_BOOLEAN))>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div class="form-group m-b-10">
                                        <label class="control-label" for="status_comment">Comentario (opcional)</label>
                                        <textarea name="comment" id="status_comment" class="form-control" rows="3"
                                            placeholder="Agrega contexto para el cambio…"></textarea>
                                    </div>
                                @endif
                                <div class="form-group m-b-0">
                                    <button type="submit" class="btn btn-sm btn-primary btn-block">
                                        <i class="voyager-check" aria-hidden="true"></i> Actualizar estado del ticket
                                    </button>
                                </div>
                            </form>
                        </x-centinela.support.compass-block>
                    </div>
                @endcan

                @can('assign', $ticket)
                    <div class="{{ $supportActionColClass }}">
                        <x-centinela.support.compass-block title="Asignacion" icon="voyager-people" class="m-b-0">
                            <form method="post" action="{{ route('centinela.support.assign.update', $ticket) }}"
                                class="form-horizontal">
                                @csrf
                                @method('PATCH')
                                @if (!$isAdmin)
                                    <input type="hidden" name="is_internal" value="0">
                                @endif
                                <div class="form-group m-b-10">
                                    <label class="control-label" for="assigned_to_user_id">Usuario a asignar</label>
                                    <select name="assigned_to_user_id" id="assigned_to_user_id" class="form-control">
                                        <option value="">— Sin asignar —</option>
                                        @foreach ($assignableUsers as $u)
                                            <option value="{{ $u['id'] }}" @selected($ticket->assigned_to_user_id == $u['id'])>
                                                {{ $u['label'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                @if ($isAdmin)
                                    <div class="form-group m-b-10">
                                        <div class="row support-comment-vis-row">
                                            <div class="col-sm-8 col-xs-12 support-comment-vis-col-comment">
                                                <label class="control-label" for="assign_comment">Comentario
                                                    (opcional)</label>
                                                <textarea name="comment" id="assign_comment" class="form-control" rows="3"
                                                    placeholder="Agrega contexto para la asignacion…"></textarea>
                                            </div>
                                            <div class="col-sm-4 col-xs-12">
                                                <label class="control-label" for="assign_is_internal">Visibilidad</label>
                                                <div class="support-visibilidad-toggle-wrap">
                                                    <input type="checkbox" name="is_internal" id="assign_is_internal"
                                                        value="1" class="toggleswitch support-visibilidad-toggle"
                                                        data-on="Interno" data-off="Publico" data-onstyle="warning"
                                                        data-offstyle="default" @checked(filter_var(old('is_internal', false), FILTER_VALIDATE_BOOLEAN))>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div class="form-group m-b-10">
                                        <label class="control-label" for="assign_comment">Comentario (opcional)</label>
                                        <textarea name="comment" id="assign_comment" class="form-control" rows="3"
                                            placeholder="Agrega contexto para la asignacion…"></textarea>
                                    </div>
                                @endif
                                <div class="form-group m-b-0">
                                    <button type="submit" class="btn btn-sm btn-primary btn-block">
                                        <i class="voyager-forward" aria-hidden="true"></i> Asignar ticket
                                    </button>
                                </div>
                            </form>
                        </x-centinela.support.compass-block>
                    </div>
                @endcan
            </div>
        @endif

        {{-- Panel Hilo: columna izquierda hilo con scroll, derecha respuesta (md+) --}}
        @php
            $canReplyInThread = auth()->user()->can('reply', $ticket);
            $hiloThreadColClass = $canReplyInThread ? 'col-md-7 col-sm-12 support-hilo-thread-col' : 'col-sm-12';
        @endphp
        <x-centinela.support.compass-block title="Hilo" icon="voyager-bubble" class="m-b-20">
            @php
                $events = $isAdmin ? $ticket->events : $ticket->events->where('is_internal', false);

                $items = collect();

                foreach ($events as $ev) {
                    $items->push([
                        'kind' => 'event',
                        'created_at' => $ev->created_at,
                        'data' => $ev,
                    ]);
                }

                foreach ($ticket->messages->slice(1) as $msg) {
                    $items->push([
                        'kind' => 'message',
                        'created_at' => $msg->created_at,
                        'data' => $msg,
                    ]);
                }

                $items = $items->sortBy('created_at')->values();
            @endphp

            <div class="row support-hilo-layout">
                <div class="{{ $hiloThreadColClass }} support-hilo-thread-col">
                    <div class="support-hilo-scroll-outer">
                        <div class="support-hilo-thread-scroll" id="thread_scroll_root"
                            aria-label="Mensajes e historial del ticket">
                            <div class="support-thread-inner support-thread-wrapper">
                                @if ($items->isEmpty())
                                    <p class="text-muted support-thread-empty m-b-0">Aun no hay actividad en el hilo.
                                    </p>
                                @else
                                    @foreach ($items as $item)
                                        @if ($item['kind'] === 'message')
                                            @php
                                                /** @var \App\Models\SupportTicketMessage $msg */
                                                $msg = $item['data'];
                                                $msgUserId = $msg->user_id;
                                                $msgUserName = $msg->user?->name ?? '—';
                                                $isSelf = $msgUserId === $currentUserId;
                                            @endphp
                                            <div
                                                class="support-thread-message {{ $isSelf ? 'support-thread-message--self' : '' }}">
                                                <div class="support-avatar support-avatar--{{ $msgUserId }}"
                                                    style="background:{{ avatarColor($msgUserId, $avatarPalette) }}">
                                                    {{ avatarInitials($msgUserName) }}
                                                </div>
                                                <div class="support-thread-message-body">
                                                    <div class="support-thread-header">
                                                        <span class="support-thread-author">{{ $msgUserName }}</span>
                                                        <span class="support-thread-time support-relative-time"
                                                            title="{{ $msg->created_at?->format('d-m-Y H:i') }}">
                                                            {{ $msg->created_at?->diffForHumans() }}
                                                        </span>
                                                    </div>
                                                    <div>{!! nl2br(e($msg->body)) !!}</div>
                                                </div>
                                            </div>
                                        @else
                                            @php
                                                /** @var \App\Models\SupportTicketEvent $ev */
                                                $ev = $item['data'];
                                                $evLabel = match ($ev->type) {
                                                    \App\Models\SupportTicketEvent::TYPE_STATUS_CHANGED
                                                        => 'Cambio de estado',
                                                    \App\Models\SupportTicketEvent::TYPE_ASSIGNMENT_CHANGED
                                                        => 'Cambio de asignacion',
                                                    \App\Models\SupportTicketEvent::TYPE_GITHUB_LINKED
                                                        => 'Vinculo GitHub',
                                                    default => $ev->type,
                                                };

                                                $formatUser = static function (?int $id, $eventUsersById): string {
                                                    if (!$id) {
                                                        return '—';
                                                    }

                                                    $u = $eventUsersById[$id] ?? null;
                                                    if (!$u) {
                                                        return (string) $id;
                                                    }

                                                    return trim($u->name . ' (' . ($u->email ?? '—') . ')');
                                                };

                                                $formatStatus = static function (?string $value): string {
                                                    if (!$value) {
                                                        return '—';
                                                    }

                                                    try {
                                                        return \App\Enums\SupportTicketStatus::from($value)->label();
                                                    } catch (\Throwable) {
                                                        return (string) $value;
                                                    }
                                                };
                                            @endphp
                                            <div class="support-thread-event">
                                                <span class="support-thread-event-icon">&#9881;</span>
                                                <div class="support-thread-event-body">
                                                    <div class="support-thread-header">
                                                        <span
                                                            class="support-thread-author">{{ $ev->actor?->name ?? 'Sistema' }}</span>
                                                        <span class="support-thread-time support-relative-time"
                                                            title="{{ $ev->created_at?->format('d-m-Y H:i') }}">
                                                            {{ $ev->created_at?->diffForHumans() }}
                                                        </span>
                                                        @if ($ev->is_internal)
                                                            <span class="label label-warning"
                                                                style="margin-left:6px;">Interno</span>
                                                        @endif
                                                    </div>
                                                    <strong>{{ $evLabel }}</strong>
                                                    @if (is_array($ev->meta) && isset($ev->meta['from'], $ev->meta['to']))
                                                        &mdash; {{ $formatStatus($ev->meta['from']) }} &rarr;
                                                        {{ $formatStatus($ev->meta['to']) }}
                                                    @elseif (is_array($ev->meta) && array_key_exists('from_user_id', $ev->meta))
                                                        &mdash;
                                                        {{ $formatUser(isset($ev->meta['from_user_id']) ? (int) $ev->meta['from_user_id'] : null, $eventUsersById) }}
                                                        &rarr;
                                                        {{ $formatUser(isset($ev->meta['to_user_id']) ? (int) $ev->meta['to_user_id'] : null, $eventUsersById) }}
                                                    @endif
                                                    @if ($ev->comment)
                                                        <div class="text-muted m-t-5">{!! nl2br(e($ev->comment)) !!}</div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach

                                    {{-- Boton scroll al ultimo (sticky al pie del area scroll) --}}
                                    <div class="support-scroll-bottom support-scroll-bottom--in-thread"
                                        id="scroll_bottom_btn" style="display:none;">
                                        <button type="button" class="btn btn-sm btn-success"
                                            onclick="var r=document.getElementById('thread_scroll_root');if(r){r.scrollTop=r.scrollHeight;}">
                                            <i class="voyager-angle-down" aria-hidden="true"></i> Ir al ultimo
                                        </button>
                                    </div>
                                    <div id="thread_end" tabindex="-1"></div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                @if ($canReplyInThread)
                    <div class="col-md-5 col-sm-12 support-hilo-reply-col">
                        <form method="post" action="{{ route('centinela.support.messages.store', $ticket) }}"
                            id="reply_form">
                            @csrf
                            <div class="form-group">
                                <label class="control-label" for="reply_body">Responder</label>
                                <textarea name="body" id="reply_body" class="form-control" rows="8" required maxlength="5000"
                                    placeholder="Escribe tu mensaje…"></textarea>
                                <div class="support-char-counter" id="reply_counter">0/5000</div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block" id="reply_submit_btn">
                                <i class="voyager-check" aria-hidden="true"></i> Enviar mensaje
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </x-centinela.support.compass-block>
    </div>
@stop

@section('javascript')
    <script>
        (function() {
            // Scroll al ultimo mensaje al cargar (contenedor con overflow, no la pagina)
            var scrollRoot = document.getElementById('thread_scroll_root');
            var threadEnd = document.getElementById('thread_end');
            if (scrollRoot) {
                scrollRoot.scrollTop = scrollRoot.scrollHeight;
            } else if (threadEnd) {
                threadEnd.scrollIntoView({
                    behavior: 'auto'
                });
            }

            // Boton "Ir al ultimo": visibilidad segun si el ancla final entra en el viewport del scroll
            var scrollBtn = document.getElementById('scroll_bottom_btn');

            if (threadEnd && scrollBtn && scrollRoot) {
                var observer = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        scrollBtn.style.display = entry.isIntersecting ? 'none' : 'block';
                    });
                }, {
                    root: scrollRoot,
                    rootMargin: '0px 0px 24px 0px',
                    threshold: 0.01
                });
                observer.observe(threadEnd);
            }

            // Contador de caracteres en reply
            var replyBody = document.getElementById('reply_body');
            var replyCounter = document.getElementById('reply_counter');

            function setupCharCounter(input, counter) {
                if (!input || !counter) return;
                var max = parseInt(input.getAttribute('maxlength')) || 5000;

                function update() {
                    var len = input.value.length;
                    counter.textContent = len + '/' + max;
                    counter.classList.remove('support-char-counter--warn', 'support-char-counter--danger');
                    if (len > max * 0.9) {
                        counter.classList.add('support-char-counter--danger');
                    } else if (len > max * 0.75) {
                        counter.classList.add('support-char-counter--warn');
                    }
                }
                input.addEventListener('input', update);
                update();
            }
            setupCharCounter(replyBody, replyCounter);

            // Boton reply con estado de carga
            var replyForm = document.getElementById('reply_form');
            var replySubmitBtn = document.getElementById('reply_submit_btn');

            if (replyForm && replySubmitBtn) {
                replyForm.addEventListener('submit', function() {
                    replySubmitBtn.disabled = true;
                    replySubmitBtn.innerHTML =
                        '<i class="voyager-refresh" aria-hidden="true"></i> Enviando…';
                });
            }
        })();

        $(document).ready(function() {
            if ($.fn.bootstrapToggle && $('.support-visibilidad-toggle').length) {
                $('.support-visibilidad-toggle').bootstrapToggle();
            }

            if ($.fn.select2 && $('#assigned_to_user_id').length) {
                $('#assigned_to_user_id').select2({
                    width: '100%',
                    placeholder: 'Buscar usuario…',
                    allowClear: true
                });
            }
        });
    </script>
@stop
