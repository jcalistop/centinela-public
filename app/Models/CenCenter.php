<?php

namespace App\Models;

use App\Enums\Cen\CenCenterAdmDependency;
use App\Enums\Cen\CenCenterCertification;
use App\Enums\Cen\CenCenterEmergencyType;
use App\Enums\Cen\CenCenterOrigen;
use App\Enums\Cen\CenCenterProviderType;
use App\Enums\Cen\CenCenterScope;
use App\Enums\Cen\CenCenterType;
use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Enums\Cen\CentroNivelAtencion;
use App\Enums\Cen\CentroNivelComplejidad;
use App\Enums\Cen\CentroPertenenciaSnss;
use App\Enums\Cen\CentroTieneUrgencia;
use App\Enums\Cen\CentroTipoAtencion;
use App\Enums\Cen\CentroTipoSapu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\Auditable;

class CenCenter extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use SoftDeletes;

    protected $table = 'cen_centers';

    /** @var list<string> */
    protected $guarded = ['id'];

    public $timestamps = true;

    protected $casts = [
        'origen' => CenCenterOrigen::class,
        'synced_at' => 'datetime',
        'tarea_pendiente' => 'boolean',
        'cen_type' => CenCenterType::class,
        'cen_scope' => CenCenterScope::class,
        'cen_certification' => CenCenterCertification::class,
        'cen_adm_dependency' => CenCenterAdmDependency::class,
        'cen_emergency_type' => CenCenterEmergencyType::class,
        'cen_provider_type' => CenCenterProviderType::class,
        'pertenencia_snss' => CentroPertenenciaSnss::class,
        'nivel_atencion' => CentroNivelAtencion::class,
        'tiene_urgencia' => CentroTieneUrgencia::class,
        'tipo_sapu' => CentroTipoSapu::class,
        'estado_funcionamiento' => CentroEstadoFuncionamiento::class,
        'nivel_complejidad' => CentroNivelComplejidad::class,
        'tipo_atencion' => CentroTipoAtencion::class,
    ];

    public function cenAddress(): HasOne
    {
        return $this->hasOne(CenAddress::class);
    }

    public function scopeHieFilter(Builder $query): Builder
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return $query;
        }

        return $query->where('cen_hie_dependency_id', $user->cen_hie_dependency_id);
    }

    /**
     * Centros elegibles para nueva inclusión / convenio (no aplica a configuración histórica).
     */
    public function scopeEligibleForNewConfiguration(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CenCenterNote::class);
    }

    public function adosados(): HasMany
    {
        return $this->hasMany(CenCenter::class, 'codigo_madre', 'codigo');
    }

    public function cenHieDependency(): BelongsTo
    {
        return $this->belongsTo(CenHieDependency::class, 'cen_hie_dependency_id');
    }

    public function metCenterInclusions(): HasMany
    {
        return $this->hasMany(MetCenterInclusion::class, 'cen_center_id');
    }

    public function metCenterAdosadosMadre(): HasMany
    {
        return $this->hasMany(MetCenterAdosado::class, 'cen_center_madre_id');
    }

    public function metCenterAdosadosHijo(): HasMany
    {
        return $this->hasMany(MetCenterAdosado::class, 'cen_center_adosado_id');
    }
}
