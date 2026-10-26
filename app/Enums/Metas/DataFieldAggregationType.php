<?php

namespace App\Enums\Metas;

enum DataFieldAggregationType: string
{
    case Sumatoria = 'sumatoria';
    case UltimoDato = 'ultimo_dato';

    public function label(): string
    {
        return match ($this) {
            self::Sumatoria => 'Sumatoria',
            self::UltimoDato => 'Último dato',
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

    /**
     * Indica si los valores se acumulan a lo largo de la línea temporal.
     */
    public function isAccumulative(): bool
    {
        return $this === self::Sumatoria;
    }
}
