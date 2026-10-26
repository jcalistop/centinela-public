<?php

namespace App\Models;

use App\Enums\Comges\AccionTipoMedio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class ComAction extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $casts = [
        'tipo_medio' => AccionTipoMedio::class,
    ];

    public function comEvaluations(): HasMany
    {
        return $this->hasMany(ComEvaluation::class);
    }

    public function comIndicatorCutoff(): BelongsTo
    {
        return $this->belongsTo(ComIndicatorCutoff::class);
    }

    public function comSensitivityTables(): HasMany
    {
        return $this->hasMany(ComSensitivityTable::class);
    }

    public function comSensitivityDependencies(): HasMany
    {
        return $this->hasMany(ComSensitivityDependency::class);
    }

    public function comVerifiableDocuments(): HasMany
    {
        return $this->hasMany(ComVerifiableDocument::class);
    }
}
