<?php

namespace App\Models;

use App\Enums\IndicatorType\EtapaVigente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetStageTransition extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'indicator_type_id',
        'cen_hie_dependency_id',
        'year_id',
        'etapa_desde',
        'etapa_hasta',
        'motivo',
        'user_id',
        'created_at',
    ];

    protected $casts = [
        'etapa_desde' => EtapaVigente::class,
        'etapa_hasta' => EtapaVigente::class,
        'created_at' => 'datetime',
    ];

    public function indicatorType(): BelongsTo
    {
        return $this->belongsTo(IndicatorType::class);
    }

    public function cenHieDependency(): BelongsTo
    {
        return $this->belongsTo(CenHieDependency::class, 'cen_hie_dependency_id');
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
