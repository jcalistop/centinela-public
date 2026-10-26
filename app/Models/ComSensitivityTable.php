<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class ComSensitivityTable extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $casts = [
        'meta_numero' => 'float',
    ];

    public function comAction(): BelongsTo
    {
        return $this->belongsTo(ComAction::class);
    }

    public function comSensitivityDependencies(): HasMany
    {
        return $this->hasMany(ComSensitivityDependency::class);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function normalizedValores(): array
    {
        $rawValores = json_decode((string) $this->valores, true);

        if (! is_array($rawValores)) {
            return [];
        }

        return array_values(array_map(fn (array $valor): array => self::normalizeValorRule($valor), $rawValores));
    }

    public function resolveCumplimiento(float $resultado): ?float
    {
        foreach ($this->normalizedValores() as $rule) {
            $matchesMin = $this->matchesMinBoundary($resultado, $rule['valor_minimo'], $rule['operador_minimo']);
            $matchesMax = $this->matchesMaxBoundary($resultado, $rule['valor_maximo'], $rule['operador_maximo']);

            if ($matchesMin && $matchesMax) {
                return (float) $rule['cumplimiento'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $valor
     * @return array<string, mixed>
     */
    public static function normalizeValorRule(array $valor): array
    {
        $operatorMin = isset($valor['operador_minimo'])
            ? ($valor['operador_minimo'] instanceof \BackedEnum ? $valor['operador_minimo']->value : (string) $valor['operador_minimo'])
            : '>=';
        $operatorMax = isset($valor['operador_maximo'])
            ? ($valor['operador_maximo'] instanceof \BackedEnum ? $valor['operador_maximo']->value : (string) $valor['operador_maximo'])
            : '<';

        if (! in_array($operatorMin, ['>=', '>'], true)) {
            $operatorMin = '>=';
        }

        if (! in_array($operatorMax, ['<=', '<'], true)) {
            $operatorMax = '<';
        }

        return [
            'valor_minimo' => self::toNullableFloat($valor['valor_minimo'] ?? null),
            'operador_minimo' => $operatorMin,
            'valor_maximo' => self::toNullableFloat($valor['valor_maximo'] ?? null),
            'operador_maximo' => $operatorMax,
            'cumplimiento' => self::toNullableFloat($valor['cumplimiento'] ?? null),
        ];
    }

    private static function toNullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function matchesMinBoundary(float $resultado, ?float $minValue, string $operator): bool
    {
        if ($minValue === null) {
            return true;
        }

        return $operator === '>'
            ? $resultado > $minValue
            : $resultado >= $minValue;
    }

    private function matchesMaxBoundary(float $resultado, ?float $maxValue, string $operator): bool
    {
        if ($maxValue === null) {
            return true;
        }

        return $operator === '<='
            ? $resultado <= $maxValue
            : $resultado < $maxValue;
    }
}
