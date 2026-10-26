<?php

namespace App\Http\Requests;

use App\Models\IndicatorType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIndicatorTypeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $orden = $this->input('orden');

        $this->merge([
            'view' => null,
            'estado' => (bool) (int) $this->input('estado', 0),
            'orden' => $orden === null || $orden === '' ? null : (int) $orden,
        ]);
    }

    public function authorize(): bool
    {
        // Autorización BREAD (browse/add/edit) en el controlador Voyager.
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string|\Illuminate\Contracts\Validation\Rule>>
     */
    public function rules(): array
    {
        return [
            'clave_indicador' => ['required', 'string', 'max:255', 'unique:indicator_types,clave_indicador'],
            'nombre_indicador' => ['required', 'string', 'max:255', 'unique:indicator_types,nombre_indicador'],
            'descripcion_indicador' => ['nullable', 'string'],
            'icon_class' => ['nullable', 'string', 'max:255'],
            'route' => [
                'required',
                'string',
                'max:255',
                Rule::in(IndicatorType::configMetasRouteSlugs()),
                'unique:indicator_types,route',
            ],
            'estado' => ['required', 'boolean'],
            'orden' => ['nullable', 'integer', 'min:0'],
            'measurement_granularity' => ['required', Rule::in(IndicatorType::measurementGranularityValues())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'route.in' => 'El slug debe ser uno de los instrumentos Metas habilitados en el sistema para altas nuevas.',
            'route.unique' => 'Ese slug de URL ya está asignado a otro instrumento.',
        ];
    }
}
