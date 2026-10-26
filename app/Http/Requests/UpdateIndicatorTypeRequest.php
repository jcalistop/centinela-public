<?php

namespace App\Http\Requests;

use App\Models\IndicatorType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIndicatorTypeRequest extends FormRequest
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
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string|\Illuminate\Contracts\Validation\Rule>>
     */
    public function rules(): array
    {
        $id = (int) $this->route('id', 0);
        $routeIn = $id > 0
            ? IndicatorType::metasRouteSlugsForValidation($id)
            : IndicatorType::configMetasRouteSlugs();

        return [
            'clave_indicador' => [
                'required',
                'string',
                'max:255',
                Rule::unique('indicator_types', 'clave_indicador')->ignore($id),
            ],
            'nombre_indicador' => [
                'required',
                'string',
                'max:255',
                Rule::unique('indicator_types', 'nombre_indicador')->ignore($id),
            ],
            'descripcion_indicador' => ['nullable', 'string'],
            'icon_class' => ['nullable', 'string', 'max:255'],
            'route' => [
                'required',
                'string',
                'max:255',
                Rule::in($routeIn),
                Rule::unique('indicator_types', 'route')->ignore($id),
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
            'route.in' => 'El slug de URL no es válido para este registro.',
        ];
    }
}
