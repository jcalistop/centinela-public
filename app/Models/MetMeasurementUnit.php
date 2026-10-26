<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHealthService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetMeasurementUnit extends Model
{
    use BelongsToHealthService;

    protected $fillable = [
        'cen_center_id',
        'year_id',
        'met_unit_type_id',
        'codigo',
        'nombre',
        'activo',
        'cen_hie_dependency_id',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function center(): BelongsTo
    {
        return $this->belongsTo(CenCenter::class, 'cen_center_id');
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }

    public function unitType(): BelongsTo
    {
        return $this->belongsTo(MetUnitType::class, 'met_unit_type_id');
    }

    public function cenHieDependency(): BelongsTo
    {
        return $this->belongsTo(CenHieDependency::class, 'cen_hie_dependency_id');
    }

    public function monthlyDataRows(): HasMany
    {
        return $this->hasMany(MetMonthlyData::class, 'met_measurement_unit_id');
    }

    public function indicatorConfigurations(): HasMany
    {
        return $this->hasMany(MetIndicatorConfiguration::class, 'met_measurement_unit_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(MetResult::class, 'met_measurement_unit_id');
    }

    public function scopeForCenterYear(Builder $query, int $centerId, int $yearId): Builder
    {
        return $query->where('cen_center_id', $centerId)->where('year_id', $yearId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function isInUse(): bool
    {
        return $this->usageReasons() !== [];
    }

    /**
     * @return list<string>
     */
    public function usageReasons(): array
    {
        $reasons = [];

        if ($this->monthlyDataRows()->exists()) {
            $reasons[] = 'carga mensual';
        }

        if ($this->indicatorConfigurations()->exists()) {
            $reasons[] = 'convenio configurado';
        }

        if ($this->results()->exists()) {
            $reasons[] = 'resultados calculados';
        }

        return $reasons;
    }

    public function usageBlockMessage(): string
    {
        $reasons = $this->usageReasons();

        if ($reasons === []) {
            return '';
        }

        return 'No se puede eliminar: la unidad tiene '.implode(', ', $reasons).'.';
    }
}
