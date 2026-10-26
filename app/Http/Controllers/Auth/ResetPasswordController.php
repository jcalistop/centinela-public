<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Year;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\ResetsPasswords;

class ResetPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
    */

    use ResetsPasswords;

    /**
     * Where to redirect users after resetting their password.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Set indicator_year in session after password reset and redirect.
     */
    protected function sendResetResponse($request, $response)
    {
        // Set indicator_year en la sesión si no existe
        if (! session()->has('indicator_year') || session('indicator_year') === null) {
            session(['indicator_year' => Year::getDefaultYear()]);
        }

        return redirect($this->redirectPath())
            ->with('status', trans($response));
    }
}
