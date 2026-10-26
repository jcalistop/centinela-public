<?php

namespace App\Http\Controllers;

use App\Helpers\ModuleAccess;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class AdpInstrumentController extends Controller
{
    public function index(): View
    {
        $indicatorType = IndicatorType::query()->where('route', 'adp')->firstOrFail();

        $user = $this->authUser();

        if (! ModuleAccess::canAccess('adp', null, $indicatorType)) {
            abort(403);
        }

        $setting = IndicatorTypeSetting::getData($indicatorType->id);
        $roleName = $user->role->name;

        if (! $setting->habilitado && ! in_array($roleName, ['admin', 'parametrizador', 'control-gestion'], true)) {
            abort(403, 'El instrumento no está habilitado para su Servicio de Salud en el año activo.');
        }

        return view('centinela.adp.index', compact(
            'indicatorType',
            'setting',
            'roleName',
        ));
    }

    public function toggleHabilitado(): RedirectResponse
    {
        $indicatorType = IndicatorType::query()->where('route', 'adp')->firstOrFail();
        $user = $this->authUser();

        $roleName = $user->role->name;
        abort_unless(in_array($roleName, ['admin', 'control-gestion'], true), 403);

        $setting = IndicatorTypeSetting::getData($indicatorType->id);
        $setting->update(['habilitado' => ! (bool) $setting->habilitado]);

        return redirect()
            ->route('adp.instrument.index')
            ->with('flash_adp_settings', 'Configuración ADP actualizada.');
    }

    private function authUser(): User
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }
}
