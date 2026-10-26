<?php

namespace App\Enums\Metas;

use App\Enums\Periodicidad;

/**
 * Claves de periodo del instrumento Metas tal como se almacenan (MES-01, TRIM-1, …).
 * No confundir con {@see Periodicidad}, que describe la modalidad del indicador (MENSUAL, TRIMESTRAL, …).
 */
enum MetInstrumentPeriodo: string
{
    case Mes01 = 'MES-01';
    case Mes02 = 'MES-02';
    case Mes03 = 'MES-03';
    case Mes04 = 'MES-04';
    case Mes05 = 'MES-05';
    case Mes06 = 'MES-06';
    case Mes07 = 'MES-07';
    case Mes08 = 'MES-08';
    case Mes09 = 'MES-09';
    case Mes10 = 'MES-10';
    case Mes11 = 'MES-11';
    case Mes12 = 'MES-12';
    case Trim1 = 'TRIM-1';
    case Trim2 = 'TRIM-2';
    case Trim3 = 'TRIM-3';
    case Trim4 = 'TRIM-4';
    case Sem1 = 'SEM-1';
    case Sem2 = 'SEM-2';
    case Anual = 'ANUAL';

    public function etiquetaCorta(): string
    {
        return match ($this) {
            self::Mes01 => 'Ene',
            self::Mes02 => 'Feb',
            self::Mes03 => 'Mar',
            self::Mes04 => 'Abr',
            self::Mes05 => 'May',
            self::Mes06 => 'Jun',
            self::Mes07 => 'Jul',
            self::Mes08 => 'Ago',
            self::Mes09 => 'Sep',
            self::Mes10 => 'Oct',
            self::Mes11 => 'Nov',
            self::Mes12 => 'Dic',
            self::Trim1 => '1er Trim.',
            self::Trim2 => '2do Trim.',
            self::Trim3 => '3er Trim.',
            self::Trim4 => '4to Trim.',
            self::Sem1 => '1er Sem.',
            self::Sem2 => '2do Sem.',
            self::Anual => 'Anual',
        };
    }

    /**
     * Mapa clave → etiqueta corta (JSON en modales / JS).
     *
     * @return array<string, string>
     */
    public static function cortaPorClave(): array
    {
        $map = [];
        foreach (self::cases() as $case) {
            $map[$case->value] = $case->etiquetaCorta();
        }

        return $map;
    }

    /**
     * Etiqueta para la columna «Datos al»: periodo corto o «Anual» si no hay clave o no está mapeada.
     */
    public static function etiquetaDatosAl(?string $ultimoPeriodo): string
    {
        if ($ultimoPeriodo === null || $ultimoPeriodo === '') {
            return 'Anual';
        }

        $case = self::tryFrom($ultimoPeriodo);

        return $case?->etiquetaCorta() ?? 'Anual';
    }

    /**
     * Resuelve la clave de periodo en `met_results` a partir del mes de carga y el tipo de entrada del campo.
     */
    public static function fromMonthAndInputType(int $mes, DataFieldInputType $inputType): ?self
    {
        return match ($inputType) {
            DataFieldInputType::Mensual => self::tryFrom(sprintf('MES-%02d', $mes)),
            DataFieldInputType::Trimestral => match ($mes) {
                3 => self::Trim1,
                6 => self::Trim2,
                9 => self::Trim3,
                12 => self::Trim4,
                default => null,
            },
            DataFieldInputType::Semestral => match ($mes) {
                6 => self::Sem1,
                12 => self::Sem2,
                default => null,
            },
            DataFieldInputType::Anual => $mes === 12 ? self::Anual : null,
            default => null,
        };
    }
}
