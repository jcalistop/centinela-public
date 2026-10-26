<?php

namespace App\Services\Support;

use App\Enums\SupportTicketArea;
use App\Enums\SupportTicketStatus;
use App\Mail\SupportTicketNotificationMail;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\SupportTicketReceiver;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SupportTicketLifecycleService
{
    public function __construct(
        private readonly SupportTicketAttachmentService $attachmentService,
    ) {}

    /**
     * @param  array{subject: string, body: string, area: string, indicator_type_id?: int|null, year_id?: int|null, cen_hie_dependency_id?: int|null, requester_user_id?: int|null}  $data
     */
    public function createTicket(User $creator, array $data, ?UploadedFile $attachment = null): SupportTicket
    {
        $requester = $creator;

        $requesterId = (int) ($data['requester_user_id'] ?? 0);
        if ($requesterId > 0 && ($creator->hasRole('admin') || $creator->hasRole('control-gestion'))) {
            $maybeRequester = User::query()->find($requesterId);
            if ($maybeRequester instanceof User) {
                if ($creator->hasRole('control-gestion') && (int) $maybeRequester->cen_hie_dependency_id !== (int) $creator->cen_hie_dependency_id) {
                    throw new \InvalidArgumentException('El solicitante debe pertenecer al mismo Servicio de Salud.');
                }

                $requester = $maybeRequester;
            }
        }

        $cenHieDependencyId = $creator->hasRole('admin')
            ? (int) (($data['cen_hie_dependency_id'] ?? null) ?: $requester->cen_hie_dependency_id)
            : (int) $creator->cen_hie_dependency_id;

        if ($cenHieDependencyId <= 0) {
            throw new \InvalidArgumentException('Debe indicar Servicio de Salud.');
        }

        if ($creator->hasRole('admin') && $requesterId > 0 && (int) $requester->cen_hie_dependency_id !== $cenHieDependencyId) {
            throw new \InvalidArgumentException('El solicitante debe pertenecer al Servicio de Salud seleccionado.');
        }

        return DB::transaction(function () use ($requester, $data, $cenHieDependencyId, $attachment): SupportTicket {
            $area = SupportTicketArea::from($data['area']);

            $ticket = SupportTicket::query()->create([
                'subject' => $data['subject'],
                'body' => $data['body'],
                'status' => SupportTicketStatus::Open,
                'area' => $area,
                'cen_hie_dependency_id' => $cenHieDependencyId,
                'created_by_user_id' => $requester->id,
                'assigned_to_user_id' => null,
                'indicator_type_id' => $data['indicator_type_id'] ?? null,
                'year_id' => $data['year_id'] ?? null,
            ]);

            if ($attachment instanceof UploadedFile) {
                $ticket->update([
                    'attachment' => $this->attachmentService->storeForTicket($ticket, $attachment),
                ]);
            }

            SupportTicketMessage::query()->create([
                'support_ticket_id' => $ticket->id,
                'user_id' => $requester->id,
                'body' => $data['body'],
            ]);

            $this->applyInitialAssignmentAndNotify($ticket->fresh());

            return $ticket->fresh();
        });
    }

    public function addMessage(SupportTicket $ticket, User $author, string $body): SupportTicketMessage
    {
        return SupportTicketMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $author->id,
            'body' => $body,
        ]);
    }

    /**
     * Receptores habilitados para el area / SS del ticket.
     *
     * @return Collection<int, SupportTicketReceiver>
     */
    public function resolveReceivers(SupportTicket $ticket): Collection
    {
        $area = $ticket->area;
        $areaValue = $area instanceof SupportTicketArea ? $area->value : (string) $area;

        $query = SupportTicketReceiver::query()
            ->where('enabled', true)
            ->where('area', $areaValue);

        if ($area === SupportTicketArea::Tecnico) {
            $query->where('scope', SupportTicketReceiver::SCOPE_GLOBAL);
        } else {
            $query
                ->where('scope', SupportTicketReceiver::SCOPE_SS)
                ->where('cen_hie_dependency_id', $ticket->cen_hie_dependency_id);
        }

        return $query->with('user')->get();
    }

    /**
     * Encola notificacion por correo a los receptores del ticket (creacion / evento).
     */
    public function notifyReceivers(SupportTicket $ticket, string $subject, ?string $comment = null): void
    {
        $receivers = $this->resolveReceivers($ticket);

        $ticketUrl = route('centinela.support.show', [$ticket], true);

        foreach ($receivers as $receiver) {
            $u = $receiver->user;
            if ($u === null || $u->email === '') {
                continue;
            }

            Mail::to($u->email)->queue(new SupportTicketNotificationMail($ticket, $ticketUrl, $subject, $comment));
        }
    }

    /**
     * Encola notificación para participantes del ticket:
     * - receptores configurados (área/SS)
     * - creador del ticket
     * - usuario asignado (si aplica)
     *
     * Se omite el correo del usuario que gatilla la acción (`$excludeUserId`) para evitar auto-notificaciones.
     */
    public function notifyParticipants(
        SupportTicket $ticket,
        string $subject,
        string $kind = SupportTicketNotificationMail::KIND_CREATED,
        ?string $comment = null,
        ?int $excludeUserId = null,
        ?string $commentAuthorName = null
    ): void {
        $ticket->loadMissing(['createdBy', 'assignedTo']);

        /** @var array<int, User> $targetsById */
        $targetsById = [];

        foreach ($this->resolveReceivers($ticket) as $receiver) {
            if ($receiver->user instanceof User) {
                $targetsById[$receiver->user->id] = $receiver->user;
            }
        }

        if ($ticket->createdBy instanceof User) {
            $targetsById[$ticket->createdBy->id] = $ticket->createdBy;
        }

        if ($ticket->assignedTo instanceof User) {
            $targetsById[$ticket->assignedTo->id] = $ticket->assignedTo;
        }

        $ticketUrl = route('centinela.support.show', [$ticket], true);

        foreach ($targetsById as $u) {
            if ($excludeUserId !== null && (int) $u->id === (int) $excludeUserId) {
                continue;
            }

            if ($u->email === '') {
                continue;
            }

            Mail::to($u->email)->queue(new SupportTicketNotificationMail(
                $ticket,
                $ticketUrl,
                $subject,
                $kind,
                $comment,
                $commentAuthorName
            ));
        }
    }

    /**
     * Notifica receptores configurados después de crear un ticket (correo encolable).
     */
    public function applyInitialAssignmentAndNotify(SupportTicket $ticket): void
    {
        $receivers = $this->resolveReceivers($ticket);

        $primary = $receivers->firstWhere('is_primary', true);

        if ($primary !== null && $primary->user !== null) {
            $ticket->assigned_to_user_id = $primary->user_id;
            $ticket->save();
        }

        $this->notifyParticipants(
            $ticket,
            'Nuevo ticket de soporte',
            SupportTicketNotificationMail::KIND_CREATED
        );
    }

    public function markRead(SupportTicket $ticket, User $user): void
    {
        $ticket->reads()->updateOrCreate(
            ['user_id' => $user->id],
            ['last_read_at' => now()]
        );
    }

    /**
     * @return array{has_unread: bool, last_message_at: Carbon|null}
     */
    public function unreadState(SupportTicket $ticket, User $user): array
    {
        $lastMessageAt = $ticket->messages()->max('created_at');
        $read = $ticket->reads()->where('user_id', $user->id)->first();

        if ($lastMessageAt === null) {
            return ['has_unread' => false, 'last_message_at' => null];
        }

        $last = Carbon::parse($lastMessageAt);

        if ($read === null) {
            return ['has_unread' => true, 'last_message_at' => $last];
        }

        return [
            'has_unread' => $last->isAfter($read->last_read_at),
            'last_message_at' => $last,
        ];
    }
}
