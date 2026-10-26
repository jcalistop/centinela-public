<?php

namespace App\Models;

use App\Enums\Metas\DataFieldAggregationType;
use App\Enums\Metas\DataFieldComponente;
use App\Enums\Metas\DataFieldInputType;
use App\Services\Metas\MetFieldSsConfigService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class MetDataField extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'met_indicator_id',
        'label',
        'variable_key',
        'componente',
        'input_type',
        'aggregation_type',
        'fuente',
        'orden',
    ];

    protected $casts = [
        'orden' => 'integer',
        'fuente' => 'string',
        'componente' => DataFieldComponente::class,
        'input_type' => DataFieldInputType::class,
        'aggregation_type' => DataFieldAggregationType::class,
    ];

    /**
     * Clave "{ssId}:{yearId}" cuando input/aggregation ya son tipos efectivos (sin reconsultar BD).
     */
    protected ?string $ssEffectiveKey = null;

    public function metIndicator(): BelongsTo
    {
        return $this->belongsTo(MetIndicator::class);
    }

    public function monthlyData(): HasMany
    {
        return $this->hasMany(MetMonthlyData::class, 'met_data_field_id');
    }

    public function ssConfigs(): HasMany
    {
        return $this->hasMany(MetFieldSsConfig::class);
    }

    public function markSsEffectiveTypes(int $ssId, int $yearId): void
    {
        $this->ssEffectiveKey = $ssId.':'.$yearId;
    }

    public function hasSsEffectiveTypes(int $ssId, int $yearId): bool
    {
        return $this->ssEffectiveKey === $ssId.':'.$yearId;
    }

    public function resolveInputType(int $ssId, int $yearId): DataFieldInputType
    {
        if ($this->hasSsEffectiveTypes($ssId, $yearId)) {
            return $this->input_type;
        }

        $config = app(MetFieldSsConfigService::class)->configForField((int) $this->id, $ssId, $yearId);

        if ($config !== null) {
            return $config->input_type;
        }

        return $this->input_type;
    }

    public function resolveAggregationType(int $ssId, int $yearId): ?DataFieldAggregationType
    {
        if ($this->hasSsEffectiveTypes($ssId, $yearId)) {
            return $this->aggregation_type;
        }

        $config = app(MetFieldSsConfigService::class)->configForField((int) $this->id, $ssId, $yearId);

        if ($config !== null) {
            return $config->aggregation_type;
        }

        return $this->aggregation_type;
    }
}
