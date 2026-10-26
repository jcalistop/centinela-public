<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\Auditable;

class ComVisualization extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    public function comIndicatorCutoff()
    {
        return $this->belongsTo(ComIndicatorCutoff::class);
    }

    public function scopeCenHieDependency($query)
    {
        return $query->where('cen_hie_dependency_id', Auth::user()->cen_hie_dependency_id);
    }
}
