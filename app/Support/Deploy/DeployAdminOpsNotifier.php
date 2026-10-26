<?php

declare(strict_types=1);

namespace App\Support\Deploy;

use App\Mail\DeployAdminOpsTerminalMail;
use App\Models\DeployExecution;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Correo ops a rol admin ante cierres terminales de deploy (manual/auto).
 * No sustituye la notificación oficial a control-gestión.
 */
class DeployAdminOpsNotifier
{
    public const META_NOTIFIED_FOR = 'admin_ops_notified_for';

    /**
     * @var list<string>
     */
    public const TERMINAL_STATUSES = [
        'succeeded',
        'failed',
        'failed_precondition',
        'skipped',
    ];

    public function notifyIfApplicable(DeployExecution $execution): void
    {
        $status = (string) $execution->status;
        if (! in_array($status, self::TERMINAL_STATUSES, true)) {
            return;
        }

        if (! in_array((string) $execution->mode, ['manual', 'auto'], true)) {
            return;
        }

        $meta = is_array($execution->meta) ? $execution->meta : [];
        if (($meta[self::META_NOTIFIED_FOR] ?? null) === $status) {
            return;
        }

        $recipients = $this->adminRecipients();
        if ($recipients->isEmpty()) {
            Log::info('centinela.deploy.admin_ops_mail.skipped_no_recipients', [
                'execution_id' => $execution->id,
                'status' => $status,
            ]);

            return;
        }

        try {
            $panelUrl = url('/admin');
            foreach ($recipients as $user) {
                Mail::to($user->email)->queue(new DeployAdminOpsTerminalMail(
                    executionId: (int) $execution->id,
                    mode: (string) $execution->mode,
                    status: $status,
                    summary: (string) ($execution->summary ?? ''),
                    panelUrl: $panelUrl,
                    recipientName: (string) $user->name,
                ));
            }

            $meta[self::META_NOTIFIED_FOR] = $status;
            $execution->meta = $meta;
            $execution->save();

            Log::info('centinela.deploy.admin_ops_mail.queued', [
                'execution_id' => $execution->id,
                'status' => $status,
                'recipient_count' => $recipients->count(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('centinela.deploy.admin_ops_mail.failed', [
                'execution_id' => $execution->id,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return Collection<int, User>
     */
    public function adminRecipients(): Collection
    {
        return User::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereHas('role', fn ($q) => $q->where('name', 'admin'))
            ->get();
    }
}
