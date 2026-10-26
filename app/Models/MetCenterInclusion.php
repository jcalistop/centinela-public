<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHealthService;
use App\Models\Concerns\FiltersByActiveYear;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use OwenIt\Auditing\Contracts\Auditable;

class MetCenterInclusion extends Model implements Auditable
{
    use BelongsToHealthService;
    use FiltersByActiveYear;
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'cen_center_id',
        'year_id',
        'indicator_type_id',
        'cen_hie_dependency_id',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (MetCenterInclusion $model): void {
            if (empty($model->cen_hie_dependency_id)) {
                $model->cen_hie_dependency_id = Auth::user()->cen_hie_dependency_id;
            }

            if (empty($model->year_id)) {
                $year = Session::get('indicator_year');
                if ($year instanceof Year) {
                    $model->year_id = $year->id;
                }
            }
        });
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(CenCenter::class, 'cen_center_id');
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }

    public function cenHieDependency(): BelongsTo
    {
        return $this->belongsTo(CenHieDependency::class, 'cen_hie_dependency_id');
    }
}
