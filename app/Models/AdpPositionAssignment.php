<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use OwenIt\Auditing\Contracts\Auditable;

class AdpPositionAssignment extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'user_id',
        'adp_position_id',
        'cen_center_id',
        'start_date',
        'end_date',
        'cen_hie_dependency_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('adp_assignment_hie', function (Builder $builder): void {
            if (! Auth::check()) {
                return;
            }

            $user = Auth::user();
            if ($user === null || $user->role?->name === 'admin') {
                return;
            }

            $builder->where(
                'cen_hie_dependency_id',
                $user->cen_hie_dependency_id
            );
        });

        static::saving(function (AdpPositionAssignment $model): void {
            $center = CenCenter::query()->find($model->cen_center_id);
            if ($center === null) {
                throw ValidationException::withMessages([
                    'cen_center_id' => 'El establecimiento no existe.',
                ]);
            }

            $model->cen_hie_dependency_id = $center->cen_hie_dependency_id;

            if ($model->end_date !== null && $model->start_date !== null && $model->end_date->lt($model->start_date)) {
                throw ValidationException::withMessages([
                    'end_date' => 'La fecha de término no puede ser anterior a la fecha de inicio.',
                ]);
            }

            if (Auth::check()) {
                $user = Auth::user();
                if ($user !== null && $user->role?->name !== 'admin') {
                    if ((int) $center->cen_hie_dependency_id !== (int) $user->cen_hie_dependency_id) {
                        throw ValidationException::withMessages([
                            'cen_center_id' => 'El establecimiento no pertenece a su Servicio de Salud.',
                        ]);
                    }
                }
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<AdpPosition, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(AdpPosition::class, 'adp_position_id');
    }

    /**
     * @return BelongsTo<CenCenter, $this>
     */
    public function center(): BelongsTo
    {
        return $this->belongsTo(CenCenter::class, 'cen_center_id');
    }

    /**
     * @return BelongsTo<CenHieDependency, $this>
     */
    public function cenHieDependency(): BelongsTo
    {
        return $this->belongsTo(CenHieDependency::class, 'cen_hie_dependency_id');
    }

    /**
     * @return HasMany<AdpAgreement, $this>
     */
    public function agreements(): HasMany
    {
        return $this->hasMany(AdpAgreement::class, 'adp_position_assignment_id');
    }

    public function scopeHieFilter(Builder $query): Builder
    {
        return $query->where('cen_hie_dependency_id', Auth::user()->cen_hie_dependency_id);
    }
}
