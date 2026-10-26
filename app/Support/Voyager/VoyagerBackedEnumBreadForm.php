<?php

namespace App\Support\Voyager;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;

/**
 * Voyager BREAD compara valores de formulario (radio/select) con strings de details.options.
 * Los casts a backed enum devuelven objetos Enum y ninguna opción queda marcada en edit/read.
 */
final class VoyagerBackedEnumBreadForm
{
    /** @var array<int, string> */
    public const CUTOFF_BREAD_ENUM_FIELDS = [
        'clave',
        'tipo_corte',
    ];

    /** @var array<int, string> */
    public const CEN_CENTER_BREAD_ENUM_FIELDS = [
        'nivel_atencion',
        'nivel_complejidad',
        'pertenencia_snss',
        'tiene_urgencia',
        'tipo_atencion',
        'tipo_sapu',
        'estado_funcionamiento',
    ];

    /**
     * @param  array<int, string>  $fields
     */
    public static function exposeStringValues(Model $model, array $fields): void
    {
        $stringCasts = [];

        foreach ($fields as $field) {
            $value = $model->getAttribute($field);

            if ($value instanceof BackedEnum) {
                $stringCasts[$field] = 'string';
            }
        }

        if ($stringCasts === []) {
            return;
        }

        $model->mergeCasts($stringCasts);

        foreach (array_keys($stringCasts) as $field) {
            $raw = $model->getRawOriginal($field);

            if ($raw !== null) {
                $model->setAttribute($field, $raw);
            }
        }
    }

    public static function displayLabel(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return '—';
    }
}
