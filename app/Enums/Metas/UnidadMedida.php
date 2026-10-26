<?php

namespace App\Enums\Metas;

enum UnidadMedida: string
{
    case Porcentaje = '%';
    case Casos = 'casos';
    case Dias = 'dias';
    case Personas = 'personas';
    case Atenciones = 'atenciones';
    case Promedio = 'promedio';
    case Ratio = 'ratio';
    case Indice = 'indice';

    public function label(): string
    {
        return match ($this) {
            self::Porcentaje => 'Porcentaje (%)',
            self::Casos => 'Casos',
            self::Dias => 'Días',
            self::Personas => 'Personas',
            self::Atenciones => 'Atenciones',
            self::Promedio => 'Promedio',
            self::Ratio => 'Ratio',
            self::Indice => 'Índice',
        };
    }

    public static function tryFromStored(?string $value): ?self
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $resolved = self::tryFrom(trim($value));

        if ($resolved !== null) {
            return $resolved;
        }

        return match (mb_strtolower(trim($value))) {
            'porcentaje', 'por ciento' => self::Porcentaje,
            'días', 'día', 'dia', 'día(s)' => self::Dias,
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
