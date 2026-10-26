<?php

namespace App\Support\Metas;

use App\Enums\Metas\DataFieldComponente;
use App\Models\MetDataField;
use Illuminate\Support\Collection;

class MetIndicatorPrimaryComponentPresenter
{
    /**
     * @param  Collection<int, MetDataField>  $fields
     */
    public static function usesComplexPrimaryComponents(Collection $fields): bool
    {
        return $fields->where('componente', DataFieldComponente::Numerador)->count() !== 1
            || $fields->where('componente', DataFieldComponente::Denominador)->count() !== 1;
    }
}
