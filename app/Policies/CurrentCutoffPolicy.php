<?php

namespace App\Policies;

use App\Helpers\Helper;
use App\Models\ComAction;
use App\Models\ComIndicatorSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use TCG\Voyager\Contracts\User;

class CurrentCutoffPolicy
{
    public function currentAddEdit(User $user, int|string|null $action_id, string $etapa_vigente): bool
    {
        if (is_null($action_id)) {
            return false;
        }
        $comAction = ComAction::find($action_id);
        $now = Carbon::now();
        $hora_apertura = Carbon::createFromTimeString('08:00');
        $hora_cierre = Carbon::createFromTimeString('17:00');

        // EVALUA CONFIGURACION DEL COMGES-AÑO
        $indicatorTypeSetting = $comAction->comIndicatorCutoff->comIndicator->comCommitment->indicatorType->indicatorTypeSettings
            ->where('year_id', Helper::showCurrentYear()->id)
            ->where('cen_hie_dependency_id', Auth::user()->cen_hie_dependency_id)
            ->first();
        // EVALUA SI ESTA CONFIGURADO EL TIPO DE INDICADOR
        if (! is_null($indicatorTypeSetting)) {
            // EVALUA SI ESTA HABILITADO
            $currentEnabled = boolval($indicatorTypeSetting->habilitado);

            // //EVALUA CORTE VIGENTE
            // $currentCutoff = $comAction->comIndicatorCutoff->cutoff_id == $indicatorTypeSetting->cutoff_id;

            // EVALUA ETAPA VIGENTE
            $currentStage = $indicatorTypeSetting->etapa_vigente->value === $etapa_vigente;

            $currentIndicatorTypeSetting = $currentEnabled && $currentStage; // && $currentCutoff;
        } else {
            $currentIndicatorTypeSetting = false;
        }

        // EVALUA CONFIGURACION DEL INDICADOR
        $comIndicatorSetting = $comAction->comIndicatorCutoff->comIndicator->comIndicatorSettings
            ->where('cen_hie_dependency_id', Auth::user()->cen_hie_dependency_id)
            ->first();

        // EVALUA SI ESTA CONFIGURADO EL INDICADOR
        if (! is_null($comIndicatorSetting)) {
            // EVALUA USUARIOS
            $currentUser = $comIndicatorSetting->users->contains(Auth::user());

            // EVALUA FECHAS TOPE
            if ($comIndicatorSetting->mantener_abierto) {
                $currentInterval = true; // Si mantener abierto, no evalua fechas
            } elseif (! is_null($comIndicatorSetting->fecha_apertura) && ! is_null($comIndicatorSetting->fecha_cierre)) {
                $fecha_apertura = Carbon::parse($comIndicatorSetting->fecha_apertura)->setTime($hora_apertura->hour, $hora_apertura->minute, $hora_apertura->second);
                $fecha_cierre = Carbon::parse($comIndicatorSetting->fecha_cierre)->setTime($hora_cierre->hour, $hora_cierre->minute, $hora_cierre->second);
                $currentInterval = $now->between($fecha_apertura, $fecha_cierre);
            } else {
                $currentInterval = false;
            }

            // EVALUA CORTE VIGENTE
            $pestanasEditablesArray = json_decode($comIndicatorSetting->pestanas_editables ?? '[]', true);
            if (! is_array($pestanasEditablesArray)) {
                $pestanasEditablesArray = [];
            }
            $pestanas_editables = collect($pestanasEditablesArray);
            $currentCutoff = $pestanas_editables->values()->contains($comAction->comIndicatorCutoff->id);
            $currentComIndicatorSetting = $currentUser && $currentInterval && $currentCutoff;
        } else {
            $currentComIndicatorSetting = false;
        }

        if (Auth::user()->role->name == 'admin' || Auth::user()->role->name == 'control-gestion') {
            return $currentIndicatorTypeSetting;
        } elseif (Auth::user()->role->name == 'referente' & ($etapa_vigente == 'AUTOEVALUACION' | $etapa_vigente == 'APELACION')) {
            return $currentComIndicatorSetting && $currentIndicatorTypeSetting;
        } else {
            return false;
        }
    }

    public function currentDownload(User $user, int $com_indicator_id): bool
    {
        if (Auth::user()->role->name == 'admin' || Auth::user()->role->name == 'control-gestion') {
            return true;
        } elseif (Auth::user()->role->name == 'referente') {
            $comIndicatorSetting = ComIndicatorSetting::firstWhere([
                'com_indicator_id' => $com_indicator_id,
                'cen_hie_dependency_id' => Auth::user()->cen_hie_dependency_id,
            ]);
            $users = $comIndicatorSetting?->users;

            return is_null($users) ? false : $users->contains(Auth::user());
        } else {
            return false;
        }
    }
}
