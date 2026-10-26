<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use OwenIt\Auditing\Contracts\Auditable;

class AdpAgreementPeriod extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'adp_agreement_id',
        'sequence',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * @return BelongsTo<AdpAgreement, $this>
     */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(AdpAgreement::class, 'adp_agreement_id');
    }

    /**
     * @return HasMany<AdpObjective, $this>
     */
    public function objectives(): HasMany
    {
        return $this->hasMany(AdpObjective::class, 'adp_agreement_period_id')->orderBy('orden');
    }

    /**
     * @return HasMany<AdpMonthlyData, $this>
     */
    public function monthlyDataRows(): HasMany
    {
        return $this->hasMany(AdpMonthlyData::class, 'adp_agreement_period_id');
    }

    /**
     * @return HasOne<AdpMidtermEvaluation, $this>
     */
    public function midtermEvaluation(): HasOne
    {
        return $this->hasOne(AdpMidtermEvaluation::class, 'adp_agreement_period_id');
    }

    /**
     * Primer día de cada mes calendario que intersecta [start_date, end_date].
     *
     * @return Collection<int, Carbon>
     */
    public function monthStartsForDataEntry(): Collection
    {
        $start = $this->start_date->copy()->startOfDay();
        $end = $this->end_date->copy()->startOfDay();
        $out = collect();
        $m = $start->copy()->firstOfMonth();

        while ($m->lte($end)) {
            $monthEnd = $m->copy()->endOfMonth()->startOfDay();
            if ($monthEnd->gte($start) && $m->lte($end)) {
                $out->push($m->copy()->startOfDay());
            }
            $m->addMonth();
        }

        return $out;
    }

    public function expectedMidtermDate(): Carbon
    {
        return $this->start_date->copy()->addMonths(6)->startOfDay();
    }

    /**
     * @return HasOne<AdpAgreementPeriodResult, $this>
     */
    public function calculationResult(): HasOne
    {
        return $this->hasOne(AdpAgreementPeriodResult::class, 'adp_agreement_period_id');
    }

    /**
     * @return HasMany<AdpIndicatorResult, $this>
     */
    public function indicatorResults(): HasMany
    {
        return $this->hasMany(AdpIndicatorResult::class, 'adp_agreement_period_id');
    }

    /**
     * @return HasMany<AdpPreliminarySnapshot, $this>
     */
    public function preliminarySnapshots(): HasMany
    {
        return $this->hasMany(AdpPreliminarySnapshot::class, 'adp_agreement_period_id')->orderByDesc('version');
    }

    /**
     * @return HasMany<AdpIndicatorAppeal, $this>
     */
    public function indicatorAppeals(): HasMany
    {
        return $this->hasMany(AdpIndicatorAppeal::class, 'adp_agreement_period_id')->orderByDesc('id');
    }

    /**
     * @return HasMany<AdpFinalResult, $this>
     */
    public function finalResults(): HasMany
    {
        return $this->hasMany(AdpFinalResult::class, 'adp_agreement_period_id')->orderByDesc('version');
    }
}
