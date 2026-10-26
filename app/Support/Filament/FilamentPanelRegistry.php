<?php

namespace App\Support\Filament;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Panel;

/**
 * Matriz panel×rol, homes y etiquetas (SDD-284 / domain-rules § Paneles Filament).
 */
final class FilamentPanelRegistry
{
    public const SERVICIO_SALUD = 'servicio-salud';

    public const CATALOGO = 'catalogo';

    public const PAIS = 'pais';

    public const CONFIGURACION = 'configuracion';

    /**
     * @var list<string>
     */
    public const ALL = [
        self::SERVICIO_SALUD,
        self::CATALOGO,
        self::PAIS,
        self::CONFIGURACION,
    ];

    /**
     * @var array<string, list<string>>
     */
    private const ROLES_BY_PANEL = [
        self::SERVICIO_SALUD => ['visualizador', 'referente', 'control-gestion', 'admin'],
        self::CATALOGO => ['parametrizador', 'admin'],
        self::PAIS => ['parametrizador', 'admin'],
        self::CONFIGURACION => ['admin'],
    ];

    /**
     * @var array<string, string>
     */
    private const LABELS = [
        self::SERVICIO_SALUD => 'Operación SS',
        self::CATALOGO => 'Catálogo',
        self::PAIS => 'País',
        self::CONFIGURACION => 'Configuración',
    ];

    /**
     * Prioridad de home multi-rol (excepto solo-admin → configuracion).
     *
     * @var list<string>
     */
    private const HOME_PRIORITY = [
        self::SERVICIO_SALUD,
        self::PAIS,
        self::CATALOGO,
        self::CONFIGURACION,
    ];

    public static function label(string $panelId): string
    {
        return self::LABELS[$panelId] ?? $panelId;
    }

    /**
     * @return list<string>
     */
    public static function rolesFor(string $panelId): array
    {
        return self::ROLES_BY_PANEL[$panelId] ?? [];
    }

    public static function userCanAccessPanel(?User $user, string $panelId): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        foreach (self::rolesFor($panelId) as $role) {
            if ($user->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public static function accessiblePanelIds(User $user): array
    {
        $ids = [];

        foreach (self::ALL as $panelId) {
            if (self::userCanAccessPanel($user, $panelId)) {
                $ids[] = $panelId;
            }
        }

        return $ids;
    }

    public static function homePanelIdForUser(User $user): string
    {
        if ($user->hasRole('admin')) {
            return self::CONFIGURACION;
        }

        $accessible = self::accessiblePanelIds($user);

        foreach (self::HOME_PRIORITY as $panelId) {
            if (in_array($panelId, $accessible, true)) {
                return $panelId;
            }
        }

        return self::CONFIGURACION;
    }

    public static function homeUrlForUser(User $user): string
    {
        return self::panelUrl(self::homePanelIdForUser($user));
    }

    public static function panelUrl(string $panelId): string
    {
        $panel = Filament::getPanel($panelId);

        return $panel->getUrl() ?? url('/'.$panelId);
    }

    public static function isKnownPanelId(string $panelId): bool
    {
        return in_array($panelId, self::ALL, true);
    }

    public static function panel(?Panel $panel = null): ?Panel
    {
        return $panel ?? Filament::getCurrentPanel();
    }
}
