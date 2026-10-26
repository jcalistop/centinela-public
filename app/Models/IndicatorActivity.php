<?php

namespace App\Models;

use App\Enums\Indicators\IndicatorActivityAction;
use App\Enums\Indicators\IndicatorModuleType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndicatorActivity extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'indicator_type',
        'indicator_id',
        'action',
        'description',
        'metadata',
        'is_pinned',
        'user_id',
        'created_at',
    ];

    protected $casts = [
        'indicator_type' => IndicatorModuleType::class,
        'action' => IndicatorActivityAction::class,
        'metadata' => 'array',
        'is_pinned' => 'boolean',
        'indicator_id' => 'integer',
        'user_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<IndicatorActivity>  $query
     */
    public function scopeForIndicator(Builder $query, IndicatorModuleType|string $type, int $indicatorId): Builder
    {
        $module = $type instanceof IndicatorModuleType ? $type : IndicatorModuleType::from($type);

        return $query
            ->where('indicator_type', $module)
            ->where('indicator_id', $indicatorId);
    }

    /**
     * @param  Builder<IndicatorActivity>  $query
     */
    public function scopeOrderedForTimeline(Builder $query): Builder
    {
        return $query
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at');
    }

    /**
     * @param  Builder<IndicatorActivity>  $query
     */
    public function scopeVisibleToUser(Builder $query, ?User $user): Builder
    {
        if ($user === null) {
            return $query->whereRaw('0 = 1');
        }

        $ssId = $user->cen_hie_dependency_id;

        if ($ssId === null) {
            return $query->where(function (Builder $sub) {
                $sub->whereNull('metadata->cen_hie_dependency_id')
                    ->orWhereJsonContains('metadata', ['cen_hie_dependency_id' => null]);
            });
        }

        return $query->where(function (Builder $sub) use ($ssId) {
            $sub->whereNull('metadata->cen_hie_dependency_id')
                ->orWhere('metadata->cen_hie_dependency_id', $ssId);
        });
    }
}
