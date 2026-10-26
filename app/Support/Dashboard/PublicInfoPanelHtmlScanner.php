<?php

namespace App\Support\Dashboard;

use Illuminate\Validation\ValidationException;

/**
 * Scan de HTML de paneles públicos (SDD-283).
 * Rechaza patrones de alto riesgo; permite scripts/estilos inline y assets relativos.
 */
final class PublicInfoPanelHtmlScanner
{
    /**
     * @return list<string> Mensajes de rechazo (vacío = OK).
     */
    public function violations(string $html): array
    {
        $violations = [];

        if (preg_match('/<script\b[^>]*\bsrc\s*=\s*([\'"])\s*(?:https?:)?\/\//i', $html) === 1) {
            $violations[] = 'No se permiten scripts con src externo (http/https o //). Usa scripts inline.';
        }

        if (preg_match('/\b(?:href|src|action|formaction|xlink:href)\s*=\s*([\'"])\s*javascript:/i', $html) === 1) {
            $violations[] = 'No se permiten URLs javascript: en atributos.';
        }

        if (
            preg_match('/<meta\b[^>]*http-equiv\s*=\s*([\'"])refresh\1[^>]*>/i', $html) === 1
            && preg_match('/<meta\b[^>]*content\s*=\s*([\'"])[^\'"]*url\s*=\s*(?:https?:)?\/\//i', $html) === 1
        ) {
            $violations[] = 'No se permiten meta refresh hacia URLs externas.';
        }

        if (preg_match('/<base\b[^>]*\bhref\s*=\s*([\'"])\s*(?:https?:)?\/\//i', $html) === 1) {
            $violations[] = 'No se permite base href absoluto externo; Centinela inyecta la base.';
        }

        if (preg_match('/<(?:iframe|object|embed)\b/i', $html) === 1) {
            $violations[] = 'No se permiten iframe, object ni embed dentro del panel.';
        }

        return $violations;
    }

    public function assertClean(string $html): void
    {
        $violations = $this->violations($html);
        if ($violations === []) {
            return;
        }

        throw ValidationException::withMessages([
            'html_path' => implode(' ', $violations),
        ]);
    }
}
