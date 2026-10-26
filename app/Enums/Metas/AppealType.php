<?php

namespace App\Enums\Metas;

enum AppealType: string
{
    case CausaExterna = 'CAUSA_EXTERNA';
    case CausaInterna = 'CAUSA_INTERNA';

    public function label(): string
    {
        return match ($this) {
            self::CausaExterna => 'Por causa externa',
            self::CausaInterna => 'Por causa interna',
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
