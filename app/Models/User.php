<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\CustomResetPassword;
use App\Support\Configuration\FilamentChasisGate;
use App\Support\Filament\FilamentPanelRegistry;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use OwenIt\Auditing\Contracts\Auditable;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Traits\VoyagerUser;

/**
 * Usuario de aplicación (Voyager + Sanctum + auditoría).
 *
 * Métodos de roles/permisos provienen del trait {@see VoyagerUser}
 * usado por {@see \TCG\Voyager\Models\User}.
 *
 * @method bool hasRole(string|array<int, string> $name)
 * @method bool hasPermission(string $name)
 * @method bool hasPermissionOrFail(string $name)
 * @method mixed hasPermissionOrAbort(string $name, int $statusCode = 403)
 * @method $this setRole(string $name)
 */
class User extends \TCG\Voyager\Models\User implements Auditable, FilamentUser, HasAvatar
{
    use HasApiTokens, HasFactory, Notifiable;
    use \OwenIt\Auditing\Auditable;
    /**
     * BUG PENDIENTE: modelo User no se registra en Audits
     */

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new CustomResetPassword($token));
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'unidad',
        'anexo',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function getCenHieDependencyIdAttribute(mixed $value): ?int
    {
        return $value !== null ? (int) $value : null;
    }

    public function scopeOnlySelfUsers(Builder $query): Builder
    {
        return $query->where('cen_hie_dependency_id', Auth::user()->cen_hie_dependency_id);
    }

    /**
     * Scope BREAD Voyager users (SDD-085 + SDD-175): admin ve todos;
     * cualquier otro rol solo usuarios de su Servicio de Salud.
     * Los roles protegidos siguen visibles en browse para CG; editar/eliminar se bloquea en policy.
     */
    public function scopeVoyagerActorManageable(Builder $query): Builder
    {
        $actor = Auth::user();
        if (! $actor instanceof self) {
            return $query->whereRaw('1 = 0');
        }

        if ($actor->hasRole('admin')) {
            return $query;
        }

        $ssId = $actor->cen_hie_dependency_id;
        if ($ssId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('cen_hie_dependency_id', $ssId);
    }

    public function cenHieDependency(): BelongsTo
    {
        return $this->belongsTo(CenHieDependency::class);
    }

    /**
     * Misma resolución que el avatar de Voyager (URL absoluta o disco Voyager).
     */
    public function getFilamentAvatarUrl(): ?string
    {
        $storedAvatar = trim((string) ($this->avatar ?? ''));

        if ($storedAvatar === '') {
            return null;
        }

        if (Str::startsWith($storedAvatar, ['http://', 'https://'])) {
            return $storedAvatar;
        }

        $url = (string) Voyager::image($storedAvatar);

        return $url !== '' ? $url : null;
    }

    /**
     * Acceso a paneles Filament (SDD-284): maestro ∧ matriz rol→panel.
     * Flags de superficie no entran aquí.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if (! FilamentChasisGate::enabled()) {
            return false;
        }

        $panelId = $panel->getId();

        if (! FilamentPanelRegistry::isKnownPanelId($panelId)) {
            return false;
        }

        return FilamentPanelRegistry::userCanAccessPanel($this, $panelId);
    }
}
