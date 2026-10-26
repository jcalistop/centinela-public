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

class MetPreliminaryEvaluation extends Model implements Auditable
{
    use BelongsToHealthService;
    use FiltersByActiveYear;
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'met_indicator_id',
        'cen_center_id',
        'year_id',
        'cen_hie_dependency_id',
        'numerador',
        'denominador',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'numerador' => 'decimal:4',
        'denominador' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $evaluation): void {
            if (empty($evaluation->cen_hie_dependency_id) && Auth::check()) {
                $evaluation->cen_hie_dependency_id = Auth::user()->cen_hie_dependency_id;
            }

            if (empty($evaluation->year_id)) {
                $year = Session::get('indicator_year');
                if ($year instanceof Year) {
                    $evaluation->year_id = $year->id;
                }
            }
        });
    }

    public function metIndicator(): BelongsTo
    {
        return $this->belongsTo(MetIndicator::class);
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
