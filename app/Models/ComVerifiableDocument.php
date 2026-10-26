<?php

namespace App\Models;

use App\Enums\Comges\DocumentoVerificableType;
use App\Models\Concerns\BelongsToHealthService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\Auditable;

class ComVerifiableDocument extends Model implements Auditable
{
    use BelongsToHealthService;
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $casts = [
        'verifiable_type' => DocumentoVerificableType::class,
    ];

    public function save(array $options = [])
    {
        $this->cen_hie_dependency_id = Auth::user()->cen_hie_dependency_id;
        parent::save();
    }

    public function comAction(): BelongsTo
    {
        return $this->belongsTo(ComAction::class);
    }
}
