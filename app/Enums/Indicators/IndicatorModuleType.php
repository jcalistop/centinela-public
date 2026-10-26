<?php

namespace App\Enums\Indicators;

enum IndicatorModuleType: string
{
    case Comges = 'comges';
    case Metas = 'metas';

    public function label(): string
    {
        return match ($this) {
            self::Comges => 'COMGES',
            self::Metas => 'Metas',
        };
    }
}
