<?php

namespace App\Models;

use App\Services\Adp\AdpMidtermEvaluationValidator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\Auditable;

class AdpMidtermEvaluation extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'adp_midterm_evaluations';

    protected $fillable = [
        'adp_agreement_period_id',
        'evaluated_on',
        'status',
        'observaciones',
        'results_snapshot',
        'cen_hie_dependency_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'evaluated_on' => 'date',
        'results_snapshot' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('adp_midterm_hie', function (Builder $builder): void {
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

        static::saving(function (AdpMidtermEvaluation $row): void {
            $period = AdpAgreementPeriod::query()->find($row->adp_agreement_period_id);
            if ($period === null) {
                return;
            }

            AdpMidtermEvaluationValidator::assertEvaluatedOnAllowed($period, Carbon::parse($row->evaluated_on));

            $agreement = AdpAgreement::withoutGlobalScopes()->find($period->adp_agreement_id);
            if ($agreement !== null) {
                $assignment = AdpPositionAssignment::query()->withoutGlobalScopes()->find($agreement->adp_position_assignment_id);
                if ($assignment !== null) {
                    $row->cen_hie_dependency_id = $assignment->cen_hie_dependency_id;
                }
            }

            if (Auth::check()) {
                $uid = Auth::id();
                if (! $row->exists) {
                    $row->created_by = $uid;
                }
                $row->updated_by = $uid;
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
