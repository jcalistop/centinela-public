<?php

namespace App\Models;

use App\Helpers\Helper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class IndicatorTypeGlobalSetting extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'indicator_type_id',
        'year_id',
        'min_indicadores',
        'min_ponderacion',
        'max_ponderacion',
        'semaforo_verde_min',
        'semaforo_amarillo_min',
    ];

    protected $casts = [
        'min_indicadores' => 'integer',
        'min_ponderacion' => 'decimal:2',
        'max_ponderacion' => 'decimal:2',
        'semaforo_verde_min' => 'decimal:2',
        'semaforo_amarillo_min' => 'decimal:2',
    ];

    public static function getData(int $indicatorTypeId): self
    {
        return self::firstOrCreate(
            [
                'indicator_type_id' => $indicatorTypeId,
                'year_id' => Helper::showCurrentYear()->id,
            ],
            [
                'min_indicadores' => 4,
                'min_ponderacion' => 0.00,
                'max_ponderacion' => 100.00,
            ]
        );
    }

    public static function resolveFor(int $indicatorTypeId, int $yearId): ?self
    {
        return self::query()
            ->where('indicator_type_id', $indicatorTypeId)
            ->where('year_id', $yearId)
            ->first();
    }

    public function indicatorType(): BelongsTo
    {
        return $this->belongsTo(IndicatorType::class);
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }
}
