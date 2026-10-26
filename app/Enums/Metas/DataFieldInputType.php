<?php

namespace App\Enums\Metas;

enum DataFieldInputType: string
{
    case Mensual = 'mensual';
    case Trimestral = 'trimestral';
    case Semestral = 'semestral';
    case Anual = 'anual';
    case LineaBase = 'linea_base';

    public function label(): string
    {
        return match ($this) {
            self::Mensual => 'Mensual',
            self::Trimestral => 'Trimestral',
            self::Semestral => 'Semestral',
            self::Anual => 'Anual',
            self::LineaBase => 'Línea base',
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
     * Meses en los que se cargan datos para este tipo de entrada.
     *
     * @return list<int>
     */
    public function monthNumbers(): array
    {
        return match ($this) {
            self::Mensual => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12],
            self::Trimestral => [3, 6, 9, 12],
            self::Semestral => [6, 12],
            self::Anual => [12],
            self::LineaBase => [],
        };
    }

    /**
     * Indica si es un tipo de entrada periódico (asociado a meses específicos).
     */
    public function isPeriodic(): bool
    {
        return match ($this) {
            self::LineaBase => false,
            default => true,
        };
    }

    /**
     * Indica si este tipo de entrada requiere un tipo de agregación.
     */
    public function requiresAggregationType(): bool
    {
        return $this->isPeriodic();
    }

    /**
     * Granularidad relativa para ordenar tipos de entrada (mayor = más detalle).
     */
    public function granularity(): int
    {
        return match ($this) {
            self::Mensual => 4,
            self::Trimestral => 3,
            self::Semestral => 2,
            self::Anual => 1,
            self::LineaBase => 1,
        };
    }
}
