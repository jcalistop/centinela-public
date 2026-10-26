<?php

namespace App\Support\Cen;

use BackedEnum;

final class CenBackedEnumResolver
{
    /**
     * @param  class-string<BackedEnum>  $enumClass
     */
    public static function resolve(string $enumClass, ?string $glosa): ?BackedEnum
    {
        $normalized = CenApiGlosaNormalizer::passthrough($glosa);

        if ($normalized === null) {
            return null;
        }

        return $enumClass::tryFrom($normalized);
    }
}
