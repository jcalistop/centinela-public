<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class AdpIndicatorCatalogFormula extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'adp_indicator_catalog_formulas';

    protected $fillable = [
        'adp_indicator_catalog_item_id',
        'expression',
        'description',
        'is_active',
    ];

    protected $casts = [
        'expression' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo<AdpIndicatorCatalogItem, $this>
     */
    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(AdpIndicatorCatalogItem::class, 'adp_indicator_catalog_item_id');
    }
}
