<?php

namespace App\Enums\Metas;

enum MetDataLoadStatus: string
{
    case SinDatos = 'sin_datos';
    case Cargado = 'cargado';
    case Parcial = 'parcial';
    case ErrorCalculo = 'error_calculo';

    public function labelClass(): string
    {
        return match ($this) {
            self::SinDatos => 'label-default',
            self::Cargado => 'label-info',
            self::Parcial => 'label-warning',
            self::ErrorCalculo => 'label-danger',
        };
    }

    public function legendDescription(): string
    {
        return match ($this) {
            self::SinDatos => 'Sin datos — no hay carga mensual registrada',
            self::Cargado => 'Mes cargado — todos los campos requeridos con dato en el último mes',
            self::Parcial => 'Parcial — falta al menos un campo en el último mes cargado',
            self::ErrorCalculo => 'Error de cálculo — revise resultados del establecimiento',
        };
    }

    public function showsMonthSuffix(): bool
    {
        return match ($this) {
            self::Parcial, self::ErrorCalculo => true,
            default => false,
        };
    }

    public function monthSuffix(): string
    {
        return match ($this) {
            self::Parcial => 'Parcial',
            self::ErrorCalculo => 'Error',
            default => '',
        };
    }
}
