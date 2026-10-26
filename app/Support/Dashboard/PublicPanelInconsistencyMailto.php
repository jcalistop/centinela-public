<?php

namespace App\Support\Dashboard;

use App\Models\PublicInfoPanel;

/**
 * Mailto de reporte de inconsistencia en panel publicado (detalle guest, SDD-294).
 */
final class PublicPanelInconsistencyMailto
{
    public static function recipient(): ?string
    {
        $email = trim((string) config('centinela.contact_mail_to', ''));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $email;
    }

    public static function href(PublicInfoPanel $panel): ?string
    {
        $to = self::recipient();

        if ($to === null) {
            return null;
        }

        $subject = 'Inconsistencia panel: '.$panel->titulo;
        $panelUrl = route('centinela.publico.panel', ['slug' => $panel->slug], absolute: true);
        $versionLine = filled($panel->version_panel)
            ? 'Versión: '.$panel->version_panel
            : 'Versión: (no indicada)';

        $body = implode("\n", [
            'Hola, quiero reportar una inconsistencia en un panel del dashboard público de Centinela.',
            '',
            'Panel: '.$panel->titulo,
            'Slug: '.$panel->slug,
            'URL: '.$panelUrl,
            $versionLine,
            '',
            'Qué vi (describe el problema):',
            '',
            'Qué esperaba ver:',
            '',
            'SS o establecimiento (si aplica):',
            '',
            'Contacto (correo y/o teléfono):',
            '',
            'Gracias.',
        ]);

        return 'mailto:'.$to
            .'?subject='.rawurlencode($subject)
            .'&body='.rawurlencode($body);
    }
}
