<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CenProvince extends Model
{
    protected $table = 'cen_provinces';

    public $timestamps = true;

    public function cenRegion(): BelongsTo
    {
        return $this->belongsTo(CenRegion::class);
    }
}
