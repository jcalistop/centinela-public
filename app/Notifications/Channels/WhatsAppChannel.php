<?php

namespace App\Notifications\Channels;

use App\Services\WhatsAppService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class WhatsAppChannel
{
    protected WhatsAppService $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    /**
     * Enviar la notificación dada.
     */
    public function send($notifiable, Notification $notification): void
    {
        // Verificar si WhatsApp está habilitado
        if (! config('services.whatsapp.enabled', false)) {
            Log::debug('WhatsApp deshabilitado en configuración, omitiendo notificación', [
                'notification' => get_class($notification),
            ]);

            return;
        }

        // Verificar si el servicio está configurado
        if (! $this->whatsappService->isConfigured()) {
            Log::warning('Servicio de WhatsApp no configurado, omitiendo notificación', [
                'notification' => get_class($notification),
                'notifiable' => get_class($notifiable),
            ]);

            return;
        }

        // Verificar si la notificación tiene método toWhatsApp
        if (! method_exists($notification, 'toWhatsApp')) {
            Log::error('La notificación no implementa el método toWhatsApp', [
                'notification' => get_class($notification),
            ]);

            return;
        }

        try {
            // Obtener el mensaje de WhatsApp
            $message = $notification->toWhatsApp($notifiable);

            if (empty($message)) {
                Log::warning('Mensaje de WhatsApp vacío, omitiendo envío', [
                    'notification' => get_class($notification),
                ]);

                return;
            }

            // Obtener el número de teléfono del notifiable o usar configuración por defecto
            $to = method_exists($notifiable, 'routeNotificationForWhatsapp')
                ? $notifiable->routeNotificationForWhatsapp($notification)
                : config('services.whatsapp.alert_to');

            if (empty($to)) {
                Log::error('No se pudo determinar el número de destino para WhatsApp', [
                    'notification' => get_class($notification),
                    'notifiable' => get_class($notifiable),
                ]);

                return;
            }

            // Enviar el mensaje
            $sent = $this->whatsappService->sendMessage($to, $message);

            if (! $sent) {
                Log::error('Falló el envío de mensaje de WhatsApp', [
                    'notification' => get_class($notification),
                    'to' => $to,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Excepción al enviar notificación por WhatsApp', [
                'notification' => get_class($notification),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
