<?php

namespace App\Support\Dashboard;

/**
 * Mailto de solicitud de publicación de panel (landing pública, SDD-289).
 */
final class PublicPanelSubmissionMailto
{
    public static function recipient(): ?string
    {
        // Setting BD `notifications.contact_mail_to` → config via SettingsBridge.
        $email = trim((string) config('centinela.contact_mail_to', ''));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $email;
    }

    public static function href(): ?string
    {
        $to = self::recipient();

        if ($to === null) {
            return null;
        }

        $subject = 'Solicitud de publicación de panel — Centinela';
        $body = implode("\n", [
            'Hola, quiero proponer un panel para el dashboard público de Centinela.',
            '',
            'Nombre del panel:',
            'Institución / equipo:',
            'Contacto (correo y teléfono):',
            'URL o descripción del entregable (HTML/CSV/PNG):',
            '',
            'Confirmo que el panel:',
            '- Se actualizará periódicamente (si no, puede bajarse).',
            '- No incluye datos personales (PII); solo agregados públicos.',
            '- Tiene alcance nacional con desagregación por SS y/o establecimiento.',
            '- Indica fuente y metodología.',
            '- Es compatible con la curaduría (HTML/CSV/PNG) y el aislamiento del iframe.',
            '- Tiene un responsable de mantención.',
            '- Cuenta con autorización para republicarse en Centinela.',
            '',
            'Gracias.',
        ]);

        return 'mailto:'.$to
            .'?subject='.rawurlencode($subject)
            .'&body='.rawurlencode($body);
    }
}
