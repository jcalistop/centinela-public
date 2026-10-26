<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\Auditable;

class AdpAgreement extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'adp_position_assignment_id',
        'duration_years',
        'agreement_start',
        'status',
        'cen_hie_dependency_id',
    ];

    protected $casts = [
        'agreement_start' => 'date',
        'duration_years' => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('adp_agreement_hie', function (Builder $builder): void {
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

        static::saving(function (AdpAgreement $model): void {
            $assignment = AdpPositionAssignment::query()->withoutGlobalScopes()->find($model->adp_position_assignment_id);
            if ($assignment !== null) {
                $model->cen_hie_dependency_id = $assignment->cen_hie_dependency_id;
            }
        });
    }

    /**
     * @return BelongsTo<AdpPositionAssignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(AdpPositionAssignment::class, 'adp_position_assignment_id');
    }

    /**
     * @return HasMany<AdpAgreementPeriod, $this>
     */
    public function periods(): HasMany
    {
        return $this->hasMany(AdpAgreementPeriod::class, 'adp_agreement_id')->orderBy('sequence');
    }

    public function agreementEndDate(): Carbon
    {
        return Carbon::parse($this->agreement_start)->addYears($this->duration_years)->subDay();
    }
}
