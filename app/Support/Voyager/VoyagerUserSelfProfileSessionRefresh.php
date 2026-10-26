<?php

namespace App\Support\Voyager;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Tras cambiar contraseña en autoedición de perfil, re-sincroniza guard Voyager y regenera ID de sesión
 * para evitar bucles login ↔ dashboard en IIS (SDD-130).
 */
final class VoyagerUserSelfProfileSessionRefresh
{
    public static function refreshAfterPasswordChange(User $user): void
    {
        $guard = (string) app('VoyagerGuard');

        Auth::guard($guard)->login($user);
        request()->session()->regenerate();
    }
}
