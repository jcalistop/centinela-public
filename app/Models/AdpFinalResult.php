<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\Auditable;

class AdpFinalResult extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'adp_final_results';

    protected $fillable = [
        'adp_agreement_period_id',
        'adp_preliminary_snapshot_id',
        'version',
        'weighted_global_percent',
        'published_without_preliminary',
        'published_without_preliminary_reason',
        'published_at',
        'published_by_user_id',
        'cen_hie_dependency_id',
    ];

    protected $casts = [
        'version' => 'integer',
        'weighted_global_percent' => 'decimal:4',
        'published_without_preliminary' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('adp_final_res_hie', function (Builder $builder): void {
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

        static::saving(function (AdpFinalResult $row): void {
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
     * @return BelongsTo<User, $this>
     */
    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }

    /**
     * @return HasMany<AdpFinalResultRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(AdpFinalResultRow::class, 'adp_final_result_id');
    }

    /**
     * @return HasMany<AdpResultOverride, $this>
     */
    public function overrides(): HasMany
    {
        return $this->hasMany(AdpResultOverride::class, 'adp_final_result_id');
    }
}
