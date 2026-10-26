<?php

namespace App\Models;

use App\Enums\Metas\DataFieldAggregationType;
use App\Enums\Metas\DataFieldInputType;
use App\Models\Concerns\BelongsToHealthService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class MetFieldSsConfig extends Model implements Auditable
{
    use BelongsToHealthService;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'met_field_ss_configs';

    protected $fillable = [
        'met_data_field_id',
        'cen_hie_dependency_id',
        'year_id',
        'input_type',
        'aggregation_type',
    ];

    protected $casts = [
        'input_type' => DataFieldInputType::class,
        'aggregation_type' => DataFieldAggregationType::class,
    ];

    public function metDataField(): BelongsTo
    {
        return $this->belongsTo(MetDataField::class);
    }

    public function cenHieDependency(): BelongsTo
    {
        return $this->belongsTo(CenHieDependency::class);
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }
}
