<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class TwoLevelJsonStructure implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Permitir JSON vacío
        if (trim($value) === '{}' || trim($value) === '[]') {
            return;
        }

        // Asegúrate de que es un JSON válido
        $decodedData = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $fail('El campo :attribute debe ser un JSON válido.');

            return;
        }

        // 1. Verificar que sea un array/objeto y no esté vacío en el primer nivel
        if (! is_array($decodedData) || empty($decodedData)) {
            $fail('La estructura del campo :attribute no se ajusta al tipo de gráfico. Vea la estructura de ejemplo.');

            // $fail('El campo :attribute debe tener al menos un elemento en el primer nivel.');
            return;
        }

        foreach ($decodedData as $firstLevelKey => $firstLevelValue) {
            // 2. Verificar que el valor del primer nivel sea un array/objeto
            if (! is_array($firstLevelValue)) {
                $fail('La estructura del campo :attribute no se ajusta al tipo de gráfico. Vea la estructura de ejemplo.');

                // $fail("El valor de '{$firstLevelKey}' en el primer nivel de :attribute debe ser un objeto o array.");
                return;
            }

            // 3. Verificar que el segundo nivel no esté vacío
            if (empty($firstLevelValue)) {
                $fail('La estructura del campo :attribute no se ajusta al tipo de gráfico. Vea la estructura de ejemplo.');

                // $fail("El elemento '{$firstLevelKey}' en el primer nivel de :attribute debe tener al menos un elemento en el segundo nivel.");
                return;
            }

            // 4. Puedes agregar validación adicional para el tipo de datos en el segundo nivel si lo necesitas
            // Por ejemplo, si siempre debe ser un entero:
            foreach ($firstLevelValue as $secondLevelKey => $secondLevelValue) {
                // Si quieres que el valor final no sea un array/objeto, es decir, sea un "final leaf"
                if (is_array($secondLevelValue) || is_object($secondLevelValue)) {
                    $fail('La estructura del campo :attribute no se ajusta al tipo de gráfico. Vea la estructura de ejemplo.');

                    // $fail("El valor final de '{$secondLevelKey}' dentro de '{$firstLevelKey}' en el segundo nivel de :attribute no debe ser un array u objeto.");
                    return;
                }
            }
        }

    }
}
