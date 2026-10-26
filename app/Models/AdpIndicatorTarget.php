<?php

namespace App\Models;

use App\Enums\MetaOperator;
use App\Services\Adp\AdpPeriodWeightsValidator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class AdpIndicatorTarget extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'adp_indicator_id',
        'weight_percent',
        'target_value',
        'target_operador',
        'notes',
    ];

    protected $casts = [
        'weight_percent' => 'decimal:2',
        'target_value' => 'decimal:4',
        'target_operador' => MetaOperator::class,
    ];

    protected static function booted(): void
    {
        static::saving(function (AdpIndicatorTarget $target): void {
            $period = self::resolvePeriod($target);
            if ($period !== null) {
                AdpPeriodWeightsValidator::validateAgreementPeriod($period, $target);
            }
        });

        static::deleted(function (AdpIndicatorTarget $target): void {
            $period = self::resolvePeriodForDeleted($target);
            if ($period !== null) {
                AdpPeriodWeightsValidator::validateAgreementPeriodAfterDelete($period);
            }
        });
    }

    private static function resolvePeriod(AdpIndicatorTarget $target): ?AdpAgreementPeriod
    {
        $indicator = AdpIndicator::query()
            ->with('objective.period')
            ->find($target->adp_indicator_id);

        return $indicator?->objective?->period;
    }

    private static function resolvePeriodForDeleted(AdpIndicatorTarget $target): ?AdpAgreementPeriod
    {
        $indicator = AdpIndicator::query()
            ->with('objective.period')
            ->find($target->getOriginal('adp_indicator_id') ?? $target->adp_indicator_id);

        return $indicator?->objective?->period;
    }

    /**
     * @return BelongsTo<AdpIndicator, $this>
     */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(AdpIndicator::class, 'adp_indicator_id');
    }
}
