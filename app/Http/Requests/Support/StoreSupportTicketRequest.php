<?php

namespace App\Http\Requests\Support;

use App\Enums\SupportTicketArea;
use App\Models\CenHieDependency;
use App\Models\User;
use App\Services\Support\SupportTicketInstrumentOptionsService;
use App\Support\Upload\UploadSizeLimit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $areas = array_map(static fn (SupportTicketArea $a) => $a->value, SupportTicketArea::cases());

        return [
            'requester_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'area' => ['required', Rule::in($areas)],
            'attachment' => ['nullable', 'file', UploadSizeLimit::laravelMaxRule()],
            'indicator_type_id' => ['nullable', 'integer', 'exists:indicator_types,id'],
            'year_id' => ['nullable', 'integer', 'exists:years,id'],
            'cen_hie_dependency_id' => [
                Rule::requiredIf(fn () => $this->user()?->hasRole('admin') ?? false),
                'nullable',
                'integer',
                'exists:cen_hie_dependencies,id',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'requester_user_id' => 'Crear ticket a nombre de',
            'cen_hie_dependency_id' => 'Servicio de Salud',
            'indicator_type_id' => 'Instrumento',
            'year_id' => 'Año',
            'attachment' => 'Archivo adjunto',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();
            $requesterId = (int) $this->input('requester_user_id');

            if ($requesterId > 0) {
                if (! $user || (! $user->hasRole('admin') && ! $user->hasRole('control-gestion'))) {
                    $validator->errors()->add('requester_user_id', 'No tienes permisos para crear tickets a nombre de otro usuario.');
                }
            }

            if ($this->user()?->hasRole('admin')) {
                $cenId = (int) $this->input('cen_hie_dependency_id');
                $ss = CenHieDependency::query()->find($cenId);
                if ($cenId > 0 && ($ss === null || ! $ss->habilitado)) {
                    $validator->errors()->add(
                        'cen_hie_dependency_id',
                        'El Servicio de Salud debe estar entre los habilitados en la plataforma.',
                    );
                }
            }

            $indicatorTypeId = (int) $this->input('indicator_type_id');
            if ($indicatorTypeId > 0) {
                $ssId = $this->resolveTicketServicioSaludId($user);
                if ($ssId <= 0) {
                    $validator->errors()->add('indicator_type_id', 'Debes seleccionar el Servicio de Salud antes del instrumento.');
                } elseif (! app(SupportTicketInstrumentOptionsService::class)->isIndicatorAllowedForServicioSalud($indicatorTypeId, $ssId, $user)) {
                    $validator->errors()->add(
                        'indicator_type_id',
                        'El instrumento no está habilitado para este Servicio de Salud.',
                    );
                }
            }
        });
    }

    private function resolveTicketServicioSaludId(?User $user): int
    {
        if (! $user instanceof User) {
            return 0;
        }

        if ($user->hasRole('admin')) {
            return (int) $this->input('cen_hie_dependency_id');
        }

        return (int) $user->cen_hie_dependency_id;
    }
}
