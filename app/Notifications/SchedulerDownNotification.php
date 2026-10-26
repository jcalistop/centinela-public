<?php

namespace App\Notifications;

use App\Notifications\Channels\WhatsAppChannel;
use Carbon\Carbon;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SchedulerDownNotification extends Notification
{
    protected int $minutesAgo;

    protected array $heartbeatData;

    /**
     * Create a new notification instance.
     */
    public function __construct(int $minutesAgo, array $heartbeatData)
    {
        $this->minutesAgo = $minutesAgo;
        $this->heartbeatData = $heartbeatData;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['mail', WhatsAppChannel::class];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $subject = '🚨 ALERTA: Scheduler de Laravel Caído - Sistema Centinela';

        return (new MailMessage)
            ->subject($subject)
            ->error()
            ->view('emails.scheduler.down', [
                'minutesAgo' => $this->minutesAgo,
                'heartbeatData' => $this->heartbeatData,
            ]);
    }

    /**
     * Get the WhatsApp representation of the notification.
     */
    public function toWhatsApp($notifiable): string
    {
        $lastRun = Carbon::parse($this->heartbeatData['last_run'])->format('d/m/Y H:i:s');
        $hostname = $this->heartbeatData['hostname'] ?? 'Desconocido';
        $environment = $this->heartbeatData['environment'] ?? 'unknown';

        return "🚨 *ALERTA: Scheduler Caído*\n\n".
               "El scheduler de Laravel no ha ejecutado en los últimos *{$this->minutesAgo} minutos*.\n\n".
               "📊 *Detalles:*\n".
               "• Último heartbeat: {$lastRun}\n".
               "• Servidor: {$hostname}\n".
               "• Ambiente: {$environment}\n\n".
               "⚠️ *Acción requerida:* Verificar el cron y el estado del servidor inmediatamente.\n\n".
               '_Sistema de Monitoreo Centinela - MINSAL_';
    }
}
