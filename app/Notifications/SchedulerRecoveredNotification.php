<?php

namespace App\Notifications;

use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SchedulerRecoveredNotification extends Notification
{
    protected int $minutesAgo;

    /**
     * Create a new notification instance.
     */
    public function __construct(int $minutesAgo)
    {
        $this->minutesAgo = $minutesAgo;
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
        $subject = '✅ Scheduler Recuperado - Sistema Centinela';

        return (new MailMessage)
            ->subject($subject)
            ->success()
            ->view('emails.scheduler.recovered', [
                'minutesAgo' => $this->minutesAgo,
                'recoveredAt' => now()->format('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Get the WhatsApp representation of the notification.
     */
    public function toWhatsApp($notifiable): string
    {
        $recoveredAt = now()->format('d/m/Y H:i:s');

        return "✅ *Scheduler Recuperado*\n\n".
               "El scheduler de Laravel ha sido recuperado y está funcionando correctamente.\n\n".
               "📊 *Detalles:*\n".
               "• Recuperado a las: {$recoveredAt}\n".
               "• Último heartbeat: hace {$this->minutesAgo} minutos\n\n".
               "✓ El sistema está operando con normalidad.\n\n".
               '_Sistema de Monitoreo Centinela - MINSAL_';
    }
}
