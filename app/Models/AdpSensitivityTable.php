<?php

namespace App\Models;

use App\Services\Adp\AdpSensitivityBoundary;
use App\Services\Adp\AdpSensitivityRowsValidator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class AdpSensitivityTable extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'adp_indicator_id',
        'nombre',
    ];

    /**
     * @return BelongsTo<AdpIndicator, $this>
     */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(AdpIndicator::class, 'adp_indicator_id');
    }

    /**
     * @return HasMany<AdpSensitivityTableRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(AdpSensitivityTableRow::class, 'adp_sensitivity_table_id')->orderBy('orden');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function normalizedRows(): array
    {
        $this->loadMissing('rows');

        return $this->rows
            ->sortBy('orden')
            ->values()
            ->map(fn (AdpSensitivityTableRow $r): array => ComSensitivityTable::normalizeValorRule([
                'valor_minimo' => $r->valor_minimo,
                'operador_minimo' => $r->operador_minimo,
                'valor_maximo' => $r->valor_maximo,
                'operador_maximo' => $r->operador_maximo,
                'cumplimiento' => $r->cumplimiento,
            ]))
            ->all();
    }

    public function validateConfiguredRanges(): void
    {
        AdpSensitivityRowsValidator::validate($this->normalizedRows());
    }

    public function resolveCumplimiento(float $resultado): ?float
    {
        foreach ($this->normalizedRows() as $rule) {
            if (AdpSensitivityBoundary::matches($resultado, $rule)) {
                return (float) $rule['cumplimiento'];
            }
        }

        return null;
    }
}
