<?php

namespace App\Providers\Filament\Concerns;

use App\Http\Middleware\RedirectIfFilamentChasisDisabled;
use App\Models\User;
use App\Support\Filament\FilamentPanelRegistry;
use Filament\Actions\Action;
use Filament\Enums\ThemeMode;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Middleware\ShareErrorsFromSession;

trait ConfiguresCentinelaPanel
{
    protected function configureCentinelaPanel(Panel $panel, string $id): Panel
    {
        return $panel
            ->id($id)
            ->path($id)
            ->brandName('Centinela')
            ->brandLogo(asset('images/logo_centinela.svg'))
            ->brandLogoHeight('2.25rem')
            ->viteTheme('resources/css/filament/filament/theme.css')
            ->defaultThemeMode(ThemeMode::Light)
            ->colors([
                'primary' => '#016cba',
                'danger' => '#fa2a00',
                'warning' => '#f0ad4e',
                'success' => '#5cb85c',
            ])
            ->darkMode(false)
            ->userMenuItems($this->userMenuItems())
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn (): string => view('filament.components.sidebar-session-user')->render(),
            )
            ->renderHook(
                PanelsRenderHook::CONTENT_BEFORE,
                fn (): string => view('filament.components.gradient-bg')->render(),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                RedirectIfFilamentChasisDisabled::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    /**
     * Menú de usuario: cambio de panel + retorno a Voyager.
     *
     * @return array<int, Action>
     */
    protected function userMenuItems(): array
    {
        return [...$this->panelSwitcherMenuItems(), $this->voyagerMenuItem()];
    }

    /**
     * @return array<int, Action>
     */
    protected function panelSwitcherMenuItems(): array
    {
        $items = [];

        foreach (FilamentPanelRegistry::ALL as $panelId) {
            $items[] = Action::make('switch_panel_'.$panelId)
                ->label('Cambiar a '.FilamentPanelRegistry::label($panelId))
                ->icon('heroicon-o-arrows-right-left')
                ->url(fn (): string => FilamentPanelRegistry::panelUrl($panelId))
                ->visible(function () use ($panelId): bool {
                    $user = Auth::user();

                    if (! $user instanceof User) {
                        return false;
                    }

                    $accessible = FilamentPanelRegistry::accessiblePanelIds($user);

                    if (count($accessible) < 2) {
                        return false;
                    }

                    if (! in_array($panelId, $accessible, true)) {
                        return false;
                    }

                    return Filament::getCurrentPanel()?->getId() !== $panelId;
                });
        }

        return $items;
    }

    /**
     * Retorno a la administración clásica; solo admin (dual-run ADR-039).
     */
    protected function voyagerMenuItem(): Action
    {
        return Action::make('go_to_voyager')
            ->label('Ir a Voyager')
            ->icon('heroicon-o-arrow-left-on-rectangle')
            ->url(fn (): string => url('/admin'))
            ->visible(function (): bool {
                $user = Auth::user();

                return $user instanceof User && $user->hasRole('admin');
            });
    }
}
