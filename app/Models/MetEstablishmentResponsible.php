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

class MetEstablishmentResponsible extends Model implements Auditable
{
    use BelongsToHealthService;
    use FiltersByActiveYear;
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'met_establishment_responsibles';

    protected $fillable = [
        'cen_center_id',
        'year_id',
        'indicator_type_id',
        'cen_hie_dependency_id',
        'user_id',
        'assigned_by',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $responsible): void {
            if (empty($responsible->cen_hie_dependency_id) && Auth::check()) {
                $responsible->cen_hie_dependency_id = Auth::user()->cen_hie_dependency_id;
            }

            if (empty($responsible->year_id)) {
                $year = Session::get('indicator_year');
                if ($year instanceof Year) {
                    $responsible->year_id = $year->id;
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

    public function indicatorType(): BelongsTo
    {
        return $this->belongsTo(IndicatorType::class);
    }

    public function cenHieDependency(): BelongsTo
    {
        return $this->belongsTo(CenHieDependency::class, 'cen_hie_dependency_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
