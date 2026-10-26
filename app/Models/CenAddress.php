<?php

namespace App\Models;

use App\Enums\Cen\DireccionVia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CenAddress extends Model
{
    protected $table = 'cen_addresses';

    /** @var list<string> */
    protected $guarded = ['id'];

    public $timestamps = true;

    protected $casts = [
        'via' => DireccionVia::class,
    ];

    public function cenCommune(): BelongsTo
    {
        return $this->belongsTo(CenCommune::class);
    }

    public function cenCenter(): BelongsTo
    {
        return $this->belongsTo(CenCenter::class);
    }
}
