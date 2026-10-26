<?php

namespace App\Support\Voyager;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class VoyagerUserPasswordValidation
{
    /**
     * En edición el formulario permite dejar contraseña en blanco; si no se saca la clave,
     * algunos flujos la envían como "" y llegan errores SQL o comportamientos ambiguos.
     */
    public static function normalizeRequestForUpdate(Request $request): void
    {
        $password = $request->input('password');
        if ($password === null || $password === '') {
            $request->request->remove('password');
        }
    }

    public static function validateForStore(Request $request): void
    {
        Validator::make(
            $request->all(),
            [
                'password' => ['required', 'string', 'min:8'],
            ],
            [
                'password.required' => 'La contraseña es obligatoria.',
                'password.min' => 'La contraseña debe tener al menos :min caracteres.',
            ]
        )->validate();
    }

    /**
     * Solo cuando el cliente envía contraseña no vacía en actualización (tras {@see normalizeRequestForUpdate}).
     */
    public static function validateForOptionalPasswordOnUpdate(Request $request): void
    {
        if (! $request->has('password')) {
            return;
        }

        Validator::make(
            $request->all(),
            [
                'password' => ['required', 'string', 'min:8'],
            ],
            [
                'password.required' => 'Si indica una contraseña nueva, no puede estar vacía.',
                'password.min' => 'La contraseña debe tener al menos :min caracteres.',
            ]
        )->validate();
    }

    /**
     * Cuando el administrador activa el envío de credenciales, la contraseña en texto plano debe estar en el request.
     */
    public static function validatePasswordRequiredWhenSendingCredentials(Request $request): void
    {
        Validator::make(
            $request->all(),
            [
                'password' => ['required', 'string', 'min:8'],
            ],
            [
                'password.required' => 'Para enviar credenciales por correo debe indicar una contraseña.',
                'password.min' => 'La contraseña debe tener al menos :min caracteres.',
            ]
        )->validate();
    }
}
