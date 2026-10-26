<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\Auditable;

class AdpAgreementPeriodResult extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'adp_agreement_period_results';

    protected $fillable = [
        'adp_agreement_period_id',
        'weighted_global_percent',
        'n_indicators',
        'n_calculated',
        'n_failed',
        'details_json',
        'cen_hie_dependency_id',
        'calculated_at',
    ];

    protected $casts = [
        'weighted_global_percent' => 'decimal:4',
        'n_indicators' => 'integer',
        'n_calculated' => 'integer',
        'n_failed' => 'integer',
        'details_json' => 'array',
        'calculated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('adp_prd_res_hie', function (Builder $builder): void {
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

        static::saving(function (AdpAgreementPeriodResult $row): void {
            $period = AdpAgreementPeriod::query()->find($row->adp_agreement_period_id);
            if ($period === null) {
                return;
            }

            $agreement = AdpAgreement::withoutGlobalScopes()->find($period->adp_agreement_id);
            if ($agreement !== null) {
                $assignment = AdpPositionAssignment::query()->withoutGlobalScopes()->find($agreement->adp_position_assignment_id);
                if ($assignment !== null) {
                    $row->cen_hie_dependency_id = $assignment->cen_hie_dependency_id;
                }
            }
        });
    }

    /**
     * @return BelongsTo<AdpAgreementPeriod, $this>
     */
    public function agreementPeriod(): BelongsTo
    {
        return $this->belongsTo(AdpAgreementPeriod::class, 'adp_agreement_period_id');
    }
}
