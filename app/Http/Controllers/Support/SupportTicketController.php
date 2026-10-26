<?php

namespace App\Http\Controllers\Support;

use App\Enums\SupportTicketArea;
use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Support\AssignSupportTicketRequest;
use App\Http\Requests\Support\StoreSupportTicketMessageRequest;
use App\Http\Requests\Support\StoreSupportTicketRequest;
use App\Http\Requests\Support\UpdateSupportTicketStatusRequest;
use App\Mail\SupportTicketNotificationMail;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\SupportTicketRead;
use App\Models\User;
use App\Models\Year;
use App\Services\Support\GithubIssueService;
use App\Services\Support\SupportTicketAssignmentService;
use App\Services\Support\SupportTicketAttachmentService;
use App\Services\Support\SupportTicketEventService;
use App\Services\Support\SupportTicketInstrumentOptionsService;
use App\Services\Support\SupportTicketLifecycleService;
use App\Support\SupportTicket\SupportTicketCatalog;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SupportTicketController extends Controller
{
    public function searchRequesters(Request $request): JsonResponse
    {
        Gate::authorize('create', SupportTicket::class);

        /** @var User $actor */
        $actor = $request->user();

        if (! $actor->hasRole('admin') && ! $actor->hasRole('control-gestion')) {
            abort(403);
        }

        $q = trim($request->string('q')->toString());
        if ($q === '') {
            return response()->json(['results' => []]);
        }

        $query = User::query()->orderBy('name');

        // Control-gestión solo puede escoger usuarios de su SS.
        if ($actor->hasRole('control-gestion') && ! $actor->hasRole('admin')) {
            $query->where('cen_hie_dependency_id', (int) $actor->cen_hie_dependency_id);
        }

        // Admin: requiere SS explícito (para elegir solicitante por SS).
        if ($actor->hasRole('admin')) {
            $ssId = (int) $request->input('ss_id');

            $ss = CenHieDependency::query()->find($ssId);
            if ($ss === null || ! $ss->habilitado) {
                return response()->json(['results' => []]);
            }

            if ($ssId <= 0) {
                return response()->json(['results' => []]);
            }

            $query->where('cen_hie_dependency_id', $ssId);
        }

        $query->where(function ($q2) use ($q): void {
            $q2->where('name', 'like', '%'.$q.'%')
                ->orWhere('email', 'like', '%'.$q.'%');
        });

        // Excluir roles fuera del módulo soporte (por consistencia)
        $query->whereDoesntHave('role', function ($r): void {
            $r->whereIn('name', ['parametrizador']);
        });

        $users = $query->limit(20)->get(['id', 'name', 'email']);

        $results = $users->map(fn (User $u): array => [
            'id' => $u->id,
            'text' => $u->name.' ('.$u->email.')',
        ])->values();

        return response()->json(['results' => $results]);
    }

    public function availableInstruments(
        Request $request,
        SupportTicketInstrumentOptionsService $instrumentOptions,
    ): JsonResponse {
        Gate::authorize('create', SupportTicket::class);

        /** @var User $user */
        $user = $request->user();

        $ssId = $user->hasRole('admin')
            ? (int) $request->input('ss_id')
            : (int) $user->cen_hie_dependency_id;

        if ($ssId <= 0) {
            return response()->json(['results' => []]);
        }

        if ($user->hasRole('admin')) {
            $ss = CenHieDependency::query()->find($ssId);
            if ($ss === null || ! $ss->habilitado) {
                return response()->json(['results' => []]);
            }
        }

        $results = $instrumentOptions->forServicioSalud($ssId, $user)
            ->map(static fn (IndicatorType $type): array => [
                'id' => $type->id,
                'label' => $type->nombre_indicador,
            ])
            ->values();

        return response()->json(['results' => $results]);
    }

    public function index(Request $request, SupportTicketLifecycleService $lifecycle): View
    {
        Gate::authorize('viewAny', SupportTicket::class);

        /** @var User $user */
        $user = $request->user();

        $query = SupportTicket::query()
            ->visibleToUser($user)
            ->with(['createdBy', 'assignedTo', 'cenHieDependency'])
            ->orderByDesc('updated_at');

        $statusFilter = $request->string('status')->toString();
        if ($statusFilter !== '') {
            $query->where('status', $statusFilter);
        }

        $searchQuery = $request->string('q')->toString();
        if ($searchQuery !== '') {
            $query->where(function ($q) use ($searchQuery): void {
                $q->where('subject', 'like', '%'.$searchQuery.'%')
                    ->orWhere('id', (int) $searchQuery > 0 ? (int) $searchQuery : 0);
            });
        }

        // TODO Soporte bandeja: evaluar si vuelve a exponerse filtro por Servicio de Salud (query `ss`) cuando el equipo producto lo requiera.
        $ssFilter = $request->string('ss')->toString();
        if ($ssFilter !== '' && $user->hasRole('admin')) {
            $query->where('cen_hie_dependency_id', (int) $ssFilter);
        }

        $tickets = $query->paginate(20)->withQueryString();

        $ticketIds = $tickets->pluck('id')->all();

        /** @var Collection<int, SupportTicketRead> $readsKeyed */
        $readsKeyed = SupportTicketRead::query()
            ->where('user_id', $user->id)
            ->whereIn('support_ticket_id', $ticketIds)
            ->get()
            ->keyBy('support_ticket_id');

        $lastMessages = [];
        if ($ticketIds !== []) {
            $lastMessages = SupportTicketMessage::query()
                ->whereIn('support_ticket_id', $ticketIds)
                ->selectRaw('support_ticket_id, max(created_at) as last_at')
                ->groupBy('support_ticket_id')
                ->pluck('last_at', 'support_ticket_id')
                ->all();
        }

        $unreadById = [];
        /** @var array<int, Carbon|null> $lastActivityAtByTicketId */
        $lastActivityAtByTicketId = [];

        foreach ($ticketIds as $tid) {
            $lastRaw = $lastMessages[$tid] ?? null;
            $lastActivityAtByTicketId[$tid] = $lastRaw !== null ? Carbon::parse($lastRaw) : null;

            if ($lastRaw === null) {
                $unreadById[$tid] = false;

                continue;
            }
            $read = $readsKeyed[$tid] ?? null;
            if ($read === null) {
                $unreadById[$tid] = true;

                continue;
            }
            $unreadById[$tid] = Carbon::parse($lastRaw)->isAfter($read->last_read_at);
        }

        $ticketCountsByStatus = [];
        foreach (SupportTicketStatus::cases() as $case) {
            $ticketCountsByStatus[$case->value] = 0;
        }

        $visibleTotalsQuery = SupportTicket::query()->visibleToUser($user);

        foreach (
            $visibleTotalsQuery->clone()
                ->toBase()
                ->selectRaw('status, COUNT(*) as cnt')
                ->groupBy('status')
                ->get() as $row
        ) {
            $statusKey = (string) $row->status;
            if (array_key_exists($statusKey, $ticketCountsByStatus)) {
                $ticketCountsByStatus[$statusKey] = (int) $row->cnt;
            }
        }

        $ticketsVisibleTotal = (int) $visibleTotalsQuery->clone()->count();

        return view('centinela.support.index', [
            'tickets' => $tickets,
            'unreadByTicketId' => $unreadById,
            'lastActivityAtByTicketId' => $lastActivityAtByTicketId,
            'statusFilter' => $statusFilter,
            'ticketCountsByStatus' => $ticketCountsByStatus,
            'ticketsVisibleTotal' => $ticketsVisibleTotal,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', SupportTicket::class);

        /** @var User $user */
        $user = $request->user();

        $years = Year::query()->orderByDesc('ano')->get();

        $ticketSsId = $user->hasRole('admin')
            ? (int) old('cen_hie_dependency_id', 0)
            : (int) $user->cen_hie_dependency_id;

        $indicatorTypes = app(SupportTicketInstrumentOptionsService::class)
            ->forServicioSalud($ticketSsId, $user);

        $serviceDependencies = [];
        if ($user->hasRole('admin')) {
            $serviceDependencies = CenHieDependency::query()
                ->where('tipo_dependencia', 'SERVICIO')
                ->where('habilitado', true)
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre']);
        }

        $areaGuidance = [];
        foreach (SupportTicketArea::cases() as $area) {
            $areaGuidance[$area->value] = [
                'heading' => SupportTicketCatalog::guidanceHeadingForArea($area),
                'items' => SupportTicketCatalog::guidanceItemsForArea($area),
            ];
        }

        return view('centinela.support.create', compact(
            'years',
            'indicatorTypes',
            'serviceDependencies',
            'areaGuidance'
        ));
    }

    public function store(StoreSupportTicketRequest $request, SupportTicketLifecycleService $lifecycle): RedirectResponse
    {
        Gate::authorize('create', SupportTicket::class);

        /** @var User $creator */
        $creator = $request->user();

        try {
            $ticket = $lifecycle->createTicket(
                $creator,
                $request->validated(),
                $request->file('attachment'),
            );
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'cen_hie_dependency_id' => [$e->getMessage()],
            ]);
        }

        return redirect()
            ->route('centinela.support.show', $ticket)
            ->with('flash_support_success', 'Ticket creado correctamente.');
    }

    public function show(Request $request, SupportTicket $supportTicket, SupportTicketLifecycleService $lifecycle): View
    {
        Gate::authorize('view', $supportTicket);

        /** @var User $user */
        $user = $request->user();

        $supportTicket->load([
            'createdBy',
            'assignedTo',
            'cenHieDependency',
            'indicatorType',
            'year',
            'messages.user',
            'events.actor',
            'githubLinkedBy',
        ]);

        $lifecycle->markRead($supportTicket, $user);

        $assignmentService = app(SupportTicketAssignmentService::class);
        $assignableUsers = [];
        if ($request->user()->can('assign', $supportTicket)) {
            $assignableUsers = $assignmentService->assignableUsers($supportTicket, $user)
                ->map(fn (User $u): array => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'label' => $u->name.' ('.$u->email.')',
                ])
                ->all();
        }

        $eventUserIds = $supportTicket->events
            ->pluck('meta')
            ->filter(fn (mixed $m): bool => is_array($m))
            ->flatMap(function (array $m): array {
                $ids = [];
                if (isset($m['from_user_id'])) {
                    $ids[] = (int) $m['from_user_id'];
                }
                if (isset($m['to_user_id'])) {
                    $ids[] = (int) $m['to_user_id'];
                }

                return $ids;
            })
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $eventUsersById = User::query()
            ->whereIn('id', $eventUserIds)
            ->get(['id', 'name', 'email'])
            ->keyBy('id');

        $showGithubPromote = $request->user()->can('support-ticket-promote-github')
            && $supportTicket->github_issue_number === null
            && ! filled($supportTicket->github_issue_url);

        return view('centinela.support.show', [
            'ticket' => $supportTicket,
            'assignableUsers' => $assignableUsers,
            'showGithubPromote' => $showGithubPromote,
            'eventUsersById' => $eventUsersById,
        ]);
    }

    public function storeMessage(
        StoreSupportTicketMessageRequest $request,
        SupportTicket $supportTicket,
        SupportTicketLifecycleService $lifecycle
    ): RedirectResponse {
        Gate::authorize('reply', $supportTicket);

        /** @var User $author */
        $author = $request->user();

        $body = (string) $request->validated()['body'];
        $lifecycle->addMessage($supportTicket, $author, $body);

        $lifecycle->notifyParticipants(
            $supportTicket,
            'Ticket #'.$supportTicket->id.' — Nuevo mensaje',
            SupportTicketNotificationMail::KIND_MESSAGE,
            $body,
            $author->id,
            $author->name
        );

        if (
            $supportTicket->status === SupportTicketStatus::Resolved
            && $author->id === $supportTicket->created_by_user_id
        ) {
            $supportTicket->status = SupportTicketStatus::InProgress;
            $supportTicket->resolved_at = null;
            $supportTicket->save();
        }

        return redirect()
            ->route('centinela.support.show', $supportTicket)
            ->with('flash_support_success', 'Mensaje publicado.');
    }

    public function updateStatus(
        UpdateSupportTicketStatusRequest $request,
        SupportTicket $supportTicket,
        SupportTicketEventService $events,
        SupportTicketLifecycleService $lifecycle
    ): RedirectResponse {
        Gate::authorize('manage', $supportTicket);

        /** @var User $actor */
        $actor = $request->user();

        $fromStatus = $supportTicket->status;

        /** @var string $status */
        $status = $request->validated()['status'];

        $newStatus = SupportTicketStatus::from($status);

        $supportTicket->status = $newStatus;

        if ($newStatus === SupportTicketStatus::Resolved) {
            $supportTicket->resolved_at = now();
        } else {
            $supportTicket->resolved_at = null;
        }

        $supportTicket->save();

        $events->recordStatusChange(
            $supportTicket,
            $actor,
            $fromStatus,
            $newStatus,
            $request->validated()['comment'] ?? null,
            (bool) ($request->validated()['is_internal'] ?? false),
        );

        if (! $request->boolean('is_internal')) {
            $lifecycle->notifyParticipants(
                $supportTicket,
                'Ticket #'.$supportTicket->id.' — Estado cambiado a '.$newStatus->label(),
                SupportTicketNotificationMail::KIND_STATUS,
                $request->validated()['comment'] ?? null,
                $actor->id,
                $actor->name
            );
        }

        return redirect()
            ->route('centinela.support.show', $supportTicket)
            ->with('flash_support_success', 'Estado actualizado.');
    }

    public function updateAssign(
        AssignSupportTicketRequest $request,
        SupportTicket $supportTicket,
        SupportTicketEventService $events,
        SupportTicketLifecycleService $lifecycle
    ): RedirectResponse {
        Gate::authorize('assign', $supportTicket);

        /** @var User $actor */
        $actor = $request->user();

        $fromId = $supportTicket->assigned_to_user_id;

        $data = $request->validated();

        $supportTicket->assigned_to_user_id = $data['assigned_to_user_id'] ?? null;
        $supportTicket->save();

        $toId = $supportTicket->assigned_to_user_id;
        $comment = $data['comment'] ?? null;
        $isInternal = (bool) ($data['is_internal'] ?? false);

        if ((int) $fromId !== (int) $toId || ($comment !== null && trim($comment) !== '')) {
            $events->recordAssignmentChange(
                $supportTicket,
                $actor,
                $fromId ? User::query()->find((int) $fromId) : null,
                $toId ? User::query()->find((int) $toId) : null,
                $comment,
                $isInternal,
            );
        }

        if (! $isInternal && $toId !== null) {
            $lifecycle->notifyParticipants(
                $supportTicket,
                'Ticket #'.$supportTicket->id.' — Asignación actualizada',
                SupportTicketNotificationMail::KIND_ASSIGNMENT,
                $comment,
                $actor->id,
                $actor->name
            );
        }

        return redirect()
            ->route('centinela.support.show', $supportTicket)
            ->with('flash_support_success', 'Asignación actualizada.');
    }

    public function storeGithubIssue(
        Request $request,
        SupportTicket $supportTicket,
        GithubIssueService $github,
        SupportTicketEventService $events
    ): RedirectResponse {
        Gate::authorize('support-ticket-promote-github');

        Gate::authorize('view', $supportTicket);

        if ($supportTicket->github_issue_number !== null || filled($supportTicket->github_issue_url)) {
            return redirect()
                ->route('centinela.support.show', $supportTicket)
                ->with('flash_support_notice', 'Este ticket ya tiene un issue de GitHub vinculado.');
        }

        try {
            $result = $github->createIssue($supportTicket, $request->user());
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('centinela.support.show', $supportTicket)
                ->withErrors(['github' => 'No se pudo crear el issue en GitHub. Revise configuración del repositorio y el token.']);
        }

        $supportTicket->github_issue_number = $result['number'];
        $supportTicket->github_issue_url = $result['html_url'];
        $supportTicket->github_linked_at = now();
        $supportTicket->github_linked_by_user_id = $request->user()?->id;
        $supportTicket->save();

        /** @var User|null $actor */
        $actor = $request->user();
        if ($actor !== null) {
            $events->recordGithubLinked(
                $supportTicket,
                $actor,
                (int) $supportTicket->github_issue_number,
                (string) $supportTicket->github_issue_url,
                null,
                true,
            );
        }

        return redirect()
            ->route('centinela.support.show', $supportTicket)
            ->with('flash_support_success', 'Issue creado en GitHub.')
            ->with('fragment', 'github-issue');
    }

    public function downloadAttachment(
        Request $request,
        SupportTicket $supportTicket,
        SupportTicketAttachmentService $attachmentService,
    ): BinaryFileResponse {
        Gate::authorize('view', $supportTicket);

        $attachment = $attachmentService->resolveForDownload($supportTicket);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');

        return response()->download(
            $disk->path($attachment['path']),
            $attachment['name'],
        );
    }
}
