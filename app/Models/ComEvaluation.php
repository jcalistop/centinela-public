<?php

namespace App\Models;

use App\Enums\Comges\EvaluacionTipo;
use App\Models\Concerns\BelongsToHealthService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ComEvaluation extends Model implements Auditable
{
    use BelongsToHealthService;
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $guarded = [];

    protected $casts = [
        'tipo_evaluacion' => EvaluacionTipo::class,
    ];

    public function comAction(): BelongsTo
    {
        return $this->belongsTo(ComAction::class);
    }
}
