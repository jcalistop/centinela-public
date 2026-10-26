<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Document extends Model
{
    public function save(array $options = [])
    {
        $this->cen_hie_dependency_id = Auth::user()->cen_hie_dependency_id;
        $this->user_id = Auth::user()->id;
        $this->year_id = session()->get('indicator_year')->id;
        parent::save();
    }

    public function scopeFilter($query)
    {
        return $query->where('year_id', session()->get('indicator_year')->id)
            ->where('cen_hie_dependency_id', Auth::user()->cen_hie_dependency_id);
    }
}
