<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHealthService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use OwenIt\Auditing\Contracts\Auditable;

class ComIndicatorSetting extends Model implements Auditable
{
    use BelongsToHealthService;
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'cen_hie_dependency_id',
        'com_indicator_id',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'com_indicator_setting_user');
    }

    public function cenHieDependency(): BelongsTo
    {
        return $this->belongsTo(CenHieDependency::class);
    }

    public function comIndicator(): BelongsTo
    {
        return $this->belongsTo(ComIndicator::class);
    }
}
