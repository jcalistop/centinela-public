<?php

namespace App\Models;

use App\Helpers\Helper;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\Auditable;

class TabVisualization extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    public function menuVisualization()
    {
        return $this->belongsTo(MenuVisualization::class);
    }

    public function scopeUserAndYear($query)
    {
        return $query->whereHas('menuVisualization', function ($subQuery) {
            $subQuery->where('cen_hie_dependency_id', Auth::user()->cen_hie_dependency_id)
                ->where('year_id', Helper::showCurrentYear()->id);
        });
    }

    protected function nombreOrden(): Attribute
    {
        $nombreMenu = $this->menuVisualization->nombre_orden ?? '';

        return Attribute::make(
            get: fn () => $nombreMenu.' > '.$this->nombre_tab,
        );
    }
}
