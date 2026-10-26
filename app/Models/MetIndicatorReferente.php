<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHealthService;
use App\Models\Concerns\FiltersByActiveYear;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class MetIndicatorReferente extends Model
{
    use BelongsToHealthService;
    use FiltersByActiveYear;
    use HasFactory;

    protected $fillable = [
        'met_indicator_id',
        'year_id',
        'cen_hie_dependency_id',
        'user_id',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (MetIndicatorReferente $referente) {
            if (empty($referente->cen_hie_dependency_id)) {
                $referente->cen_hie_dependency_id = Auth::user()->cen_hie_dependency_id;
            }

            if (empty($referente->year_id)) {
                $year = Session::get('indicator_year');
                if ($year instanceof Year) {
                    $referente->year_id = $year->id;
                }
            }
        });
    }

    public function metIndicator(): BelongsTo
    {
        return $this->belongsTo(MetIndicator::class);
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cenHieDependency(): BelongsTo
    {
        return $this->belongsTo(CenHieDependency::class, 'cen_hie_dependency_id');
    }
}
