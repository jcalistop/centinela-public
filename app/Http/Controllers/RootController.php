<?php

namespace App\Http\Controllers;

use App\Models\MenuVisualization;
use Illuminate\Support\Facades\Auth;
use TCG\Voyager\Facades\Voyager;

class RootController extends Controller
{
    public function redirectToLogin()
    {
        return redirect()->route('voyager.login');
    }

    public function menuVisualizationIndex($id)
    {
        $dataType = Voyager::model('DataType')->where('name', 'menu_visualizations')->first();
        $query = MenuVisualization::select('menu_visualizations.*')
            ->userAndYear();

        if (Auth::user()->role->name == 'admin' || Auth::user()->role->name == 'control-gestion') {
            $dataTypeContent = $query->find($id);
        } else {
            $dataTypeContent = $query->where('estado', 1)->find($id);
        }

        return view('centinela.instrumentos-locales.index', compact('dataType', 'dataTypeContent'));
    }
}
