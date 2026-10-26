<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CenCommune extends Model
{
    protected $table = 'cen_communes';

    public $timestamps = true;

    public function cenAddresses(): HasMany
    {
        return $this->hasMany(CenAddress::class);
    }

    public function cenProvince(): BelongsTo
    {
        return $this->belongsTo(cenProvince::class);
    }
}
