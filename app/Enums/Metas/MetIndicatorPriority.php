<?php

namespace App\Enums\Metas;

enum MetIndicatorPriority: string
{
    case SinPrioridad = 'sin_prioridad';
    case Baja = 'baja';
    case Media = 'media';
    case Alta = 'alta';

    public function label(): string
    {
        return match ($this) {
            self::SinPrioridad => 'Sin prioridad',
            self::Baja => 'Prioridad Baja',
            self::Media => 'Prioridad Media',
            self::Alta => 'Prioridad Alta',
        };
    }

    public function labelClass(): string
    {
        return match ($this) {
            self::SinPrioridad => 'label-default',
            self::Baja => 'label-info',
            self::Media => 'label-warning',
            self::Alta => 'label-danger',
        };
    }

    public function showsTag(): bool
    {
        return $this !== self::SinPrioridad;
    }

    public function isAlta(): bool
    {
        return $this === self::Alta;
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
