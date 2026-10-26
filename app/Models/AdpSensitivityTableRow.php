<?php

namespace App\Models;

use App\Enums\Adp\SensitivityOperator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class AdpSensitivityTableRow extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'adp_sensitivity_table_id',
        'orden',
        'valor_minimo',
        'operador_minimo',
        'valor_maximo',
        'operador_maximo',
        'cumplimiento',
    ];

    protected $casts = [
        'orden' => 'integer',
        'valor_minimo' => 'decimal:4',
        'valor_maximo' => 'decimal:4',
        'cumplimiento' => 'decimal:2',
        'operador_minimo' => SensitivityOperator::class,
        'operador_maximo' => SensitivityOperator::class,
    ];

    /**
     * @return BelongsTo<AdpSensitivityTable, $this>
     */
    public function sensitivityTable(): BelongsTo
    {
        return $this->belongsTo(AdpSensitivityTable::class, 'adp_sensitivity_table_id');
    }
}
