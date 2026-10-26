<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHealthService;
use App\Models\Concerns\FiltersByActiveYear;
use App\Models\Concerns\ScopesMetEtapaAutoevaluacion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class MetEstablishmentResult extends Model implements Auditable
{
    use BelongsToHealthService;
    use FiltersByActiveYear;
    use HasFactory;
    use \OwenIt\Auditing\Auditable;
    use ScopesMetEtapaAutoevaluacion;

    protected $fillable = [
        'cen_center_id',
        'cen_hie_dependency_id',
        'year_id',
        'indicator_type_id',
        'periodo',
        'resultado_global',
        'n_seleccionados',
        'n_calculados',
        'etapa',
    ];

    protected $casts = [
        'etapa' => 'string',
        'resultado_global' => 'decimal:4',
        'n_seleccionados' => 'integer',
        'n_calculados' => 'integer',
    ];

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
}
