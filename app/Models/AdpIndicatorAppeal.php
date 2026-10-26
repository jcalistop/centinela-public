<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\Auditable;

class AdpIndicatorAppeal extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $table = 'adp_indicator_appeals';

    protected $fillable = [
        'adp_agreement_period_id',
        'adp_preliminary_snapshot_id',
        'adp_indicator_id',
        'status',
        'motivo',
        'resolucion',
        'resolved_by_user_id',
        'resolved_at',
        'cen_hie_dependency_id',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('adp_appeal_hie', function (Builder $builder): void {
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

        static::saving(function (AdpIndicatorAppeal $row): void {
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

    /**
     * @return BelongsTo<AdpPreliminarySnapshot, $this>
     */
    public function preliminarySnapshot(): BelongsTo
    {
        return $this->belongsTo(AdpPreliminarySnapshot::class, 'adp_preliminary_snapshot_id');
    }

    /**
     * @return BelongsTo<AdpIndicator, $this>
     */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(AdpIndicator::class, 'adp_indicator_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}
