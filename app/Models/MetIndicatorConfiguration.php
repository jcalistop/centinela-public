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

class MetIndicatorConfiguration extends Model implements Auditable
{
    use BelongsToHealthService;
    use FiltersByActiveYear;
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'met_indicator_id',
        'year_id',
        'cen_hie_dependency_id',
        'cen_center_id',
        'met_measurement_unit_id',
        'configuration_unit_scope_key',
        'aplica',
        'agrupa_ss',
        'seleccionado',
        'ponderacion',
        'meta_efectiva',
    ];

    protected $casts = [
        'aplica' => 'boolean',
        'agrupa_ss' => 'boolean',
        'seleccionado' => 'boolean',
        'ponderacion' => 'decimal:2',
        'meta_efectiva' => 'decimal:4',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (MetIndicatorConfiguration $config) {
            $config->configuration_unit_scope_key = (int) ($config->met_measurement_unit_id ?? 0);

            if (empty($config->cen_hie_dependency_id)) {
                $user = Auth::user();
                if ($user instanceof User) {
                    $config->cen_hie_dependency_id = $user->cen_hie_dependency_id;
                }
            }

            if (empty($config->year_id)) {
                $year = Session::get('indicator_year');
                if ($year instanceof Year) {
                    $config->year_id = $year->id;
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

    public function cenHieDependency(): BelongsTo
    {
        return $this->belongsTo(CenHieDependency::class, 'cen_hie_dependency_id');
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(CenCenter::class, 'cen_center_id');
    }

    public function measurementUnit(): BelongsTo
    {
        return $this->belongsTo(MetMeasurementUnit::class, 'met_measurement_unit_id');
    }
}
