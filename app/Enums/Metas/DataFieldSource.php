<?php

namespace App\Enums\Metas;

enum DataFieldSource: string
{
    case RemSa = 'rem_sa';
    case RemSb = 'rem_sb';
    case RemSm = 'rem_sm';
    case RemSp = 'rem_sp';
    case RemSd = 'rem_sd';
    case Rem20 = 'rem_20';
    case Sigte = 'sigte';
    case Sigges = 'sigges';
    case Sigfe = 'sigfe';
    case Grd = 'grd';
    case Sadu = 'sadu';
    case Sirh = 'sirh';
    case Local = 'local';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::RemSa => 'REM SA',
            self::RemSb => 'REM SB',
            self::RemSm => 'REM SM',
            self::RemSp => 'REM SP',
            self::RemSd => 'REM SD',
            self::Rem20 => 'REM 20',
            self::Sigte => 'SIGTE',
            self::Sigges => 'SIGGES',
            self::Sigfe => 'SIGFE',
            self::Grd => 'GRD',
            self::Sadu => 'SADU',
            self::Sirh => 'SIRH',
            self::Local => 'LOCAL',
            self::Otro => 'OTRO',
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
