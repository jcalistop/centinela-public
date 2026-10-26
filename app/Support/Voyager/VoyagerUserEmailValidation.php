<?php

namespace App\Support\Voyager;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * El BREAD usuarios de Voyager puede no definir regla unique sobre email; sin ella MySQL
 * lanza 1062 y la petición termina en excepción no manejada.
 */
final class VoyagerUserEmailValidation
{
    public static function validate(Request $request, ?int $ignoreUserId = null): void
    {
        Validator::make(
            $request->all(),
            [
                'email' => [
                    'required',
                    'string',
                    'email',
                    'max:255',
                    Rule::unique('users', 'email')->ignore($ignoreUserId),
                ],
            ],
            [
                'email.required' => 'El correo electrónico es obligatorio.',
                'email.email' => 'El correo electrónico no tiene un formato válido.',
                'email.unique' => 'Ya existe un usuario registrado con este correo electrónico.',
            ]
        )->validate();
    }
}
