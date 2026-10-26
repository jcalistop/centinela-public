<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $apiToken;

    protected string $phoneNumberId;

    protected string $apiVersion = 'v21.0';

    protected string $baseUrl;

    public function __construct()
    {
        $this->apiToken = config('services.whatsapp.api_token');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id');
        $this->baseUrl = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}";
    }

    /**
     * Enviar un mensaje de texto simple.
     */
    public function sendMessage(string $to, string $message): bool
    {
        try {
            // Validar que el número tenga formato internacional
            $to = $this->formatPhoneNumber($to);

            // Truncar mensaje si excede el límite de WhatsApp
            if (strlen($message) > 4096) {
                $message = substr($message, 0, 4093).'...';
                Log::warning('Mensaje de WhatsApp truncado a 4096 caracteres');
            }

            $response = Http::withToken($this->apiToken)
                ->post("{$this->baseUrl}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $to,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => $message,
                    ],
                ]);

            if ($response->successful()) {
                $messageId = $response->json('messages.0.id');

                Log::info('Mensaje de WhatsApp enviado exitosamente', [
                    'to' => $to,
                    'message_id' => $messageId,
                    'message_length' => strlen($message),
                ]);

                return true;
            }

            Log::error('Error al enviar mensaje de WhatsApp', [
                'to' => $to,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Excepción al enviar mensaje de WhatsApp', [
                'to' => $to,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Enviar un mensaje usando un template pre-aprobado.
     */
    public function sendTemplate(string $to, string $templateName, array $parameters = []): bool
    {
        try {
            $to = $this->formatPhoneNumber($to);

            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => [
                        'code' => 'es', // Español
                    ],
                ],
            ];

            // Agregar parámetros si existen
            if (! empty($parameters)) {
                $payload['template']['components'] = [
                    [
                        'type' => 'body',
                        'parameters' => array_map(function ($param) {
                            return [
                                'type' => 'text',
                                'text' => (string) $param,
                            ];
                        }, $parameters),
                    ],
                ];
            }

            $response = Http::withToken($this->apiToken)
                ->post("{$this->baseUrl}/messages", $payload);

            if ($response->successful()) {
                $messageId = $response->json('messages.0.id');

                Log::info('Template de WhatsApp enviado exitosamente', [
                    'to' => $to,
                    'template' => $templateName,
                    'message_id' => $messageId,
                ]);

                return true;
            }

            Log::error('Error al enviar template de WhatsApp', [
                'to' => $to,
                'template' => $templateName,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Excepción al enviar template de WhatsApp', [
                'to' => $to,
                'template' => $templateName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Formatear número de teléfono al formato internacional.
     */
    protected function formatPhoneNumber(string $phone): string
    {
        // Remover espacios, guiones y paréntesis
        $phone = preg_replace('/[\s\-\(\)]/', '', $phone);

        // Si no empieza con +, asumir código de país de Chile
        if (! str_starts_with($phone, '+')) {
            // Si empieza con 56, agregar solo el +
            if (str_starts_with($phone, '56')) {
                $phone = '+'.$phone;
            } else {
                // Si empieza con 9, agregar +56
                $phone = '+56'.$phone;
            }
        }

        return $phone;
    }

    /**
     * Verificar si el servicio está configurado correctamente.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->apiToken) && ! empty($this->phoneNumberId);
    }
}
