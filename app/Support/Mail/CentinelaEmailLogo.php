<?php

namespace App\Support\Mail;

use Illuminate\Mail\Attachment;
use Illuminate\Mail\Message;
use Illuminate\Notifications\Messages\MailMessage;
use Symfony\Component\Mime\Email;

final class CentinelaEmailLogo
{
    public const CONTENT_ID = 'centinela-logo';

    /**
     * Ruta al logo PNG para correos (solo PNG: Gmail no soporta SVG ni URLs de intranet).
     */
    public static function path(): ?string
    {
        $path = public_path('images/centinela_logo.png');

        return is_readable($path) ? $path : null;
    }

    public static function cidSrc(): string
    {
        return 'cid:'.self::CONTENT_ID;
    }

    /**
     * Incrusta el logo en el mensaje con CID fijo (compatible con Gmail y colas).
     */
    public static function embedSrc(Message $message): ?string
    {
        $path = self::path();

        if ($path === null) {
            return null;
        }

        return $message->embed(
            Attachment::fromPath($path)
                ->as(self::CONTENT_ID)
                ->withMime('image/png')
        );
    }

    public static function embedOnSymfonyMessage(Email $email): void
    {
        $path = self::path();

        if ($path === null) {
            return;
        }

        $email->embedFromPath($path, self::CONTENT_ID, 'image/png');
    }

    /**
     * Para notificaciones encoladas: el callback se registra al construir el MailMessage en el worker.
     */
    public static function registerOnMailMessage(MailMessage $mailMessage): MailMessage
    {
        return $mailMessage->withSymfonyMessage(fn (Email $email) => self::embedOnSymfonyMessage($email));
    }
}
