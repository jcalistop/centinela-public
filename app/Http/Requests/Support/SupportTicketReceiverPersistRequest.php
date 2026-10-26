<?php

namespace App\Http\Requests\Support;

use App\Enums\SupportTicketArea;
use App\Models\CenHieDependency;
use App\Models\SupportTicketReceiver;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class SupportTicketReceiverPersistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_primary' => $this->boolean('is_primary'),
            'enabled' => $this->boolean('enabled'),
        ]);

        if ($this->input('scope') === SupportTicketReceiver::SCOPE_GLOBAL) {
            $this->merge(['cen_hie_dependency_id' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scope' => ['required', Rule::in([SupportTicketReceiver::SCOPE_GLOBAL, SupportTicketReceiver::SCOPE_SS])],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'cen_hie_dependency_id' => [
                Rule::requiredIf(fn () => $this->input('scope') === SupportTicketReceiver::SCOPE_SS),
                'nullable',
                'integer',
                Rule::exists('cen_hie_dependencies', 'id')->where(
                    fn ($q) => $q->where('tipo_dependencia', 'SERVICIO'),
                ),
            ],
            'is_primary' => ['boolean'],
            'enabled' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('scope') === SupportTicketReceiver::SCOPE_SS) {
                $cenId = (int) $this->input('cen_hie_dependency_id');
                $ss = CenHieDependency::query()->find($cenId);
                if ($ss === null || ! $ss->habilitado) {
                    $validator->errors()->add(
                        'cen_hie_dependency_id',
                        'El Servicio de Salud debe estar entre los habilitados en la plataforma.',
                    );

                    return;
                }
            }

            $userId = (int) $this->input('user_id');
            if ($userId === 0) {
                return;
            }

            $user = User::query()->with('role')->find($userId);

            if ($user === null) {
                return;
            }

            if ($this->input('scope') === SupportTicketReceiver::SCOPE_GLOBAL) {
                if ($user->role?->name !== 'admin') {
                    $validator->errors()->add(
                        'user_id',
                        'Para soporte técnico global el usuario debe tener rol administrador.',
                    );
                }

                return;
            }

            if ($user->role?->name !== 'control-gestion') {
                $validator->errors()->add(
                    'user_id',
                    'Para gestión el usuario debe tener rol control-gestión.',
                );

                return;
            }

            if ((int) $user->cen_hie_dependency_id !== (int) $this->input('cen_hie_dependency_id')) {
                $validator->errors()->add(
                    'user_id',
                    'El usuario debe pertenecer al Servicio de Salud seleccionado.',
                );
            }
        });
    }

    /**
     * @param  mixed  $key
     * @param  mixed  $default
     * @return array<string, mixed>|mixed
     */
    public function validated($key = null, $default = null)
    {
        if ($key !== null) {
            return parent::validated($key, $default);
        }

        /** @var array<string, mixed> $validated */
        $validated = parent::validated();

        $validated['area'] = ($validated['scope'] ?? null) === SupportTicketReceiver::SCOPE_SS
            ? SupportTicketArea::Gestion->value
            : SupportTicketArea::Tecnico->value;

        return $validated;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'cen_hie_dependency_id' => 'Servicio de Salud',
        ];
    }
}
