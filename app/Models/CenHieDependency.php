<?php

namespace App\Models;

use App\Enums\Cen\DependenciaTipo;
use App\Services\Auth\AccessRequestRecipientResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Alcance {@see scopeTipoServicio}: usado por Voyager (`details.scope`) al listar servicios en el BREAD usuarios.
 *
 * @property string|null $logo
 */
class CenHieDependency extends Model
{
    public const DEFAULT_LOGO_ASSET = 'images/logo_centinela.svg';

    protected $table = 'cen_hie_dependencies';

    public $timestamps = true;

    /** @var array<string, mixed> */
    protected $attributes = [
        'habilitado' => false,
    ];

    protected $casts = [
        'tipo_dependencia' => DependenciaTipo::class,
        'habilitado' => 'boolean',
    ];

    /**
     * URL pública del logo (file Voyager en disco private o asset estático por defecto).
     */
    public function logoPublicUrl(): string
    {
        $logo = is_string($this->logo) ? trim(str_replace('\\', '/', $this->logo)) : '';
        if ($logo === '') {
            return asset(self::DEFAULT_LOGO_ASSET);
        }

        $prefix = 'servicios-de-salud/';
        if (str_starts_with($logo, $prefix)) {
            $relative = substr($logo, strlen($prefix));
        } else {
            $relative = ltrim($logo, '/');
        }

        if ($relative === '' || str_contains($relative, '..')) {
            return asset(self::DEFAULT_LOGO_ASSET);
        }

        return route('centinela.storage.ss-logo', ['filepath' => $relative]);
    }

    public function setLogoAttribute(?string $value): void
    {
        if ($value === null || trim($value) === '') {
            $this->attributes['logo'] = null;

            return;
        }

        $this->attributes['logo'] = str_replace('\\', '/', trim($value));
    }

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (CenHieDependency $model): void {
            if (! $model->isServicio()) {
                return;
            }

            if (! $model->isDirty('habilitado') || ! $model->habilitado) {
                return;
            }

            if ((bool) $model->getOriginal('habilitado')) {
                return;
            }

            if (! $model->exists) {
                return;
            }

            $resolver = app(AccessRequestRecipientResolver::class);
            if ($resolver->hasPrimaryGestionReceiver((int) $model->id)) {
                return;
            }

            throw ValidationException::withMessages([
                'habilitado' => [
                    'Antes de habilitar el Servicio de Salud debe configurar un receptor primario de Gestión en Soporte → Receptores.',
                ],
            ]);
        });
    }

    public function isServicio(): bool
    {
        $tipo = $this->tipo_dependencia;

        if ($tipo instanceof DependenciaTipo) {
            return $tipo === DependenciaTipo::Servicio;
        }

        return (string) $tipo === DependenciaTipo::Servicio->value;
    }

    /**
     * Accessor para Voyager: retorna el value string del enum.
     * Sin esto, Voyager pasa el enum object a mb_strlen() en las vistas browse.
     */
    public function getTipoDependenciaAttribute(string $value): string
    {
        return $value;
    }

    /**
     * Solo filas `tipo_dependencia = SERVICIO` con `habilitado = true`.
     * Usada por Voyager via `details.scope` del BREAD usuarios.
     */
    public function scopeEnabledCentinelaSs(Builder $query): Builder
    {
        $query->where('tipo_dependencia', DependenciaTipo::Servicio->value)
            ->orderBy('codigo');

        $user = Auth::user();
        if ($user instanceof User && $user->hasRole('admin')) {
            return $query;
        }

        return $query->where('habilitado', true);
    }

    public function serviceModuleToggles(): HasMany
    {
        return $this->hasMany(ServiceModuleToggle::class);
    }

    public function enabledServiceModuleToggles(): HasMany
    {
        return $this->serviceModuleToggles()->where('enabled', true);
    }

    public function scopeTipoServicio(Builder $query): Builder
    {
        $query->where('tipo_dependencia', DependenciaTipo::Servicio->value)
            ->orderBy('codigo');

        $user = Auth::user();
        if (! $user instanceof User) {
            return $query->whereRaw('0 = 1');
        }

        if ($user->hasRole('admin')) {
            return $query;
        }

        return $query->where('id', $user->cen_hie_dependency_id);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('tipo_dependencia', DependenciaTipo::Servicio->value)
            ->where('habilitado', true)
            ->orderBy('codigo');
    }
}
