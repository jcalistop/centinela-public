<?php

namespace App\Support\Voyager;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * La columna users.name es NOT NULL; si el POST va vacío u omitido, MySQL devuelve 1048.
 */
final class VoyagerUserNameValidation
{
    public static function validate(Request $request): void
    {
        $name = trim((string) $request->input('name', ''));

        Validator::make(
            ['name' => $name],
            [
                'name' => ['required', 'string', 'max:255'],
            ],
            [
                'name.required' => 'El nombre es obligatorio.',
                'name.max' => 'El nombre no puede superar 255 caracteres.',
            ]
        )->validate();

        $request->merge(['name' => $name]);
    }
}
