<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHealthService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ComSensitivityDependency extends Model implements Auditable
{
    use BelongsToHealthService;
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'com_sensitivity_table_id',
        'com_action_id',
        'cen_hie_dependency_id',
    ];

    public function comSensitivityTable(): BelongsTo
    {
        return $this->belongsTo(ComSensitivityTable::class);
    }

    public function save(array $options = [])
    {
        // Primero, llama al método save original
        parent::save($options);

        // Luego, obtén el com_action_id actual
        $currentComActionId = $this->comSensitivityTable->comAction->id;

        // Finalmente, elimina todos los registros diferentes al actual que tengan relación con com_action_id
        self::where('id', '<>', $this->id)
            ->where('cen_hie_dependency_id', $this->cen_hie_dependency_id)
            ->whereHas('comSensitivityTable', function ($query) use ($currentComActionId) {
                $query->where('com_action_id', $currentComActionId);
            })
            ->delete();
    }
}
