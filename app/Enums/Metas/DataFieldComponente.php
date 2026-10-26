<?php

namespace App\Enums\Metas;

enum DataFieldComponente: string
{
    case Numerador = 'numerador';
    case Denominador = 'denominador';

    public function label(): string
    {
        return match ($this) {
            self::Numerador => 'Numerador',
            self::Denominador => 'Denominador',
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
