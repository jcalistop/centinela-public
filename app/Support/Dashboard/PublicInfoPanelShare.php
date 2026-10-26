<?php

namespace App\Support\Dashboard;

use App\Models\PublicInfoPanel;

/**
 * Enlace para compartir un panel (SDD-303 enmienda).
 *
 * Publicado → ruta pública del hub. No publicado → URL firmada de revisión (SDD-293).
 */
final class PublicInfoPanelShare
{
    public static function url(PublicInfoPanel $panel): string
    {
        if ($panel->publicado) {
            return route('centinela.publico.panel', ['slug' => $panel->slug]);
        }

        return PublicInfoPanelPreview::signedUrl($panel);
    }

    public static function mailtoHref(PublicInfoPanel $panel): string
    {
        return 'mailto:?subject='.rawurlencode(self::subject($panel))
            .'&body='.rawurlencode(self::body($panel));
    }

    public static function subject(PublicInfoPanel $panel): string
    {
        return $panel->publicado
            ? 'Panel público: '.$panel->titulo
            : 'Revisión panel: '.$panel->titulo;
    }

    public static function body(PublicInfoPanel $panel): string
    {
        $url = self::url($panel);

        $lines = [
            'Hola,',
            '',
            'Te comparto el enlace del panel «'.$panel->titulo.'».',
            '',
            $url,
            '',
        ];

        if ($panel->publicado) {
            $lines[] = 'El panel está publicado en el dashboard público de Centinela y no requiere inicio de sesión.';
        } else {
            $days = PublicInfoPanelPreview::ttlDays();
            $lines[] = "El enlace caduca en aproximadamente {$days} días y no requiere inicio de sesión. Solo está disponible desde la intranet Minsal.";
            $lines[] = '';
            $lines[] = 'El panel aún no está publicado en el dashboard público de Centinela.';
        }

        $lines[] = '';

        return implode("\n", $lines);
    }
}
