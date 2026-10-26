<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class MetFormula extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'met_indicator_id',
        'expression',
        'description',
    ];

    protected $casts = [
        'expression' => 'array',
    ];

    public function metIndicator(): BelongsTo
    {
        return $this->belongsTo(MetIndicator::class);
    }
}
