<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHealthService;
use App\Models\Concerns\FiltersByActiveYear;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\Auditable;

class MetCenterAdosado extends Model implements Auditable
{
    use BelongsToHealthService;
    use FiltersByActiveYear;
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'cen_center_madre_id',
        'cen_center_adosado_id',
        'year_id',
        'indicator_type_id',
        'cen_hie_dependency_id',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (MetCenterAdosado $model): void {
            if (empty($model->cen_hie_dependency_id)) {
                $user = Auth::user();
                if ($user instanceof User) {
                    $model->cen_hie_dependency_id = $user->cen_hie_dependency_id;
                }
            }

            if (empty($model->year_id)) {
                $year = session('indicator_year');
                if ($year instanceof Year) {
                    $model->year_id = $year->id;
                }
            }
        });
    }

    /**
     * @param  Builder<MetCenterAdosado>  $query
     * @return Builder<MetCenterAdosado>
     */
    public function scopeForInstrument(Builder $query, int $indicatorTypeId): Builder
    {
        return $query->where('indicator_type_id', $indicatorTypeId);
    }

    public function centerMadre(): BelongsTo
    {
        return $this->belongsTo(CenCenter::class, 'cen_center_madre_id');
    }

    public function centerAdosado(): BelongsTo
    {
        return $this->belongsTo(CenCenter::class, 'cen_center_adosado_id');
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }

    public function indicatorType(): BelongsTo
    {
        return $this->belongsTo(IndicatorType::class, 'indicator_type_id');
    }

    public function cenHieDependency(): BelongsTo
    {
        return $this->belongsTo(CenHieDependency::class, 'cen_hie_dependency_id');
    }
}
