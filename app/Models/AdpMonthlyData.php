<?php

namespace App\Models;

use App\Services\Adp\AdpMonthlyDataValidator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use OwenIt\Auditing\Contracts\Auditable;

class AdpMonthlyData extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'adp_monthly_data';

    protected $fillable = [
        'adp_indicator_id',
        'adp_agreement_period_id',
        'month_date',
        'valor',
        'observacion',
        'cen_center_id',
        'cen_hie_dependency_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'month_date' => 'date',
        'valor' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('adp_monthly_hie', function (Builder $builder): void {
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

        static::saving(function (AdpMonthlyData $row): void {
            $period = AdpAgreementPeriod::query()->find($row->adp_agreement_period_id);
            if ($period === null) {
                return;
            }

            $normalized = AdpMonthlyDataValidator::normalizeMonthDate($row->month_date);
            $row->month_date = $normalized->toDateString();

            AdpMonthlyDataValidator::assertMonthDateInPeriod($normalized, $period);

            $indicator = AdpIndicator::query()->with('objective')->find($row->adp_indicator_id);
            if ($indicator === null) {
                throw ValidationException::withMessages([
                    'adp_indicator_id' => 'Indicador no encontrado.',
                ]);
            }
            if ($indicator->objective === null
                || (int) $indicator->objective->adp_agreement_period_id !== (int) $period->id) {
                throw ValidationException::withMessages([
                    'adp_indicator_id' => 'El indicador no pertenece al periodo indicado.',
                ]);
            }

            $agreement = AdpAgreement::withoutGlobalScopes()->find($period->adp_agreement_id);
            if ($agreement !== null) {
                $assignment = AdpPositionAssignment::query()->withoutGlobalScopes()->find($agreement->adp_position_assignment_id);
                if ($assignment !== null) {
                    $row->cen_hie_dependency_id = $assignment->cen_hie_dependency_id;
                    $row->cen_center_id = $assignment->cen_center_id;
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
     * @return BelongsTo<AdpIndicator, $this>
     */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(AdpIndicator::class, 'adp_indicator_id');
    }

    /**
     * @return BelongsTo<AdpAgreementPeriod, $this>
     */
    public function agreementPeriod(): BelongsTo
    {
        return $this->belongsTo(AdpAgreementPeriod::class, 'adp_agreement_period_id');
    }

    /**
     * @return BelongsTo<CenCenter, $this>
     */
    public function center(): BelongsTo
    {
        return $this->belongsTo(CenCenter::class, 'cen_center_id');
    }
}
