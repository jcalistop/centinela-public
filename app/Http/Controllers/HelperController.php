<?php

namespace App\Http\Controllers;

use App\Models\Year;
use App\Support\YearContext;
use Illuminate\Http\Request;

class HelperController extends Controller
{
    public function setIndicatorYear(Request $request)
    {
        $currentYear = Year::where('id', $request->year_id)->firstOrFail();
        app(YearContext::class)->setCurrent($currentYear);

        return redirect()->route('voyager.dashboard');
    }
}
