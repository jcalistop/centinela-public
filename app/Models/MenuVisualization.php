<?php

namespace App\Models;

use App\Helpers\Helper;
use App\Support\Voyager\MenuVisualizationTree;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\Auditable;

class MenuVisualization extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected static function booted()
    {
        static::creating(function ($menuVisualization) {
            $menuVisualization->cen_hie_dependency_id = Auth::user()->cen_hie_dependency_id;
            $menuVisualization->year_id = Helper::showCurrentYear()->id;
            $menuVisualization->icon_class = 'voyager-angle-right';
            $menuVisualization->route = 'voyager.instrumentos-locales.show';
        });

        static::saved(function (MenuVisualization $menuVisualization): void {
            if ($menuVisualization->cen_hie_dependency_id && $menuVisualization->year_id) {
                MenuVisualizationTree::invalidate(
                    (int) $menuVisualization->cen_hie_dependency_id,
                    (int) $menuVisualization->year_id
                );
            }
        });

        static::deleted(function (MenuVisualization $menuVisualization): void {
            if ($menuVisualization->cen_hie_dependency_id && $menuVisualization->year_id) {
                MenuVisualizationTree::invalidate(
                    (int) $menuVisualization->cen_hie_dependency_id,
                    (int) $menuVisualization->year_id
                );
            }
        });
    }

    public function scopeUserAndYear(Builder $query): Builder
    {
        return $query->where('cen_hie_dependency_id', Auth::user()->cen_hie_dependency_id)
            ->where('year_id', Helper::showCurrentYear()->id);
    }

    public function tabVisualizations()
    {
        return $this->hasMany(TabVisualization::class);
    }

    public function getMenuSuperiorBrowseAttribute()
    {
        return $this->menu_superior ?? 'Sin menú superior';
    }

    protected function nombreOrden(): Attribute
    {
        return Attribute::make(
            get: fn () => is_null($this->menu_superior) ? $this->nombre_menu : $this->menu_superior.' > '.$this->nombre_menu,
        );
    }
}
