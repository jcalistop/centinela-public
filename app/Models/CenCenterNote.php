<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CenCenterNote extends Model
{
    protected $guarded = [];

    protected $table = 'cen_center_notes';

    public function cenCenter(): BelongsTo
    {
        return $this->belongsTo(CenCenter::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
