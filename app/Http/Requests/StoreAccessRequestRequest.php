<?php

namespace App\Http\Requests;

use App\Enums\Cen\DependenciaTipo;
use App\Models\CenHieDependency;
use App\Support\Auth\AccessRequestServicioSalud;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAccessRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:255', 'not_regex:/[\r\n]/'],
            'telefono' => ['nullable', 'string', 'max:30', 'regex:/^[\d\s+().-]*$/u'],
            'cargo' => ['nullable', 'string', 'max:200'],
            'cen_hie_dependency_id' => ['required', 'integer'],
            'servicio_salud_nombre' => ['nullable', 'string', 'max:200'],
            'mensaje' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $ssId = (int) $this->input('cen_hie_dependency_id');

            if ($ssId === AccessRequestServicioSalud::OTRO_ID) {
                if (trim((string) $this->input('servicio_salud_nombre')) === '') {
                    $validator->errors()->add(
                        'servicio_salud_nombre',
                        'Indique el nombre de su servicio de salud.',
                    );
                }

                return;
            }

            $exists = CenHieDependency::query()
                ->whereKey($ssId)
                ->where('tipo_dependencia', DependenciaTipo::Servicio->value)
                ->exists();

            if (! $exists) {
                $validator->errors()->add(
                    'cen_hie_dependency_id',
                    'Seleccione un servicio de salud válido.',
                );
            }
        });
    }

    public function isOtroServicioSalud(): bool
    {
        return (int) $this->input('cen_hie_dependency_id') === AccessRequestServicioSalud::OTRO_ID;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'cen_hie_dependency_id' => 'servicio de salud',
            'servicio_salud_nombre' => 'servicio de salud',
        ];
    }
}
