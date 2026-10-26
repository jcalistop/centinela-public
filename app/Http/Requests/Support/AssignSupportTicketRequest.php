<?php

namespace App\Http\Requests\Support;

use App\Models\User;
use App\Services\Support\SupportTicketAssignmentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AssignSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_internal' => $this->boolean('is_internal'),
        ]);

        if (! ($this->user()?->hasRole('admin') ?? false)) {
            $this->merge(['is_internal' => false]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'comment' => ['nullable', 'string', 'max:5000'],
            'is_internal' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $ticket = $this->route('supportTicket');
            $assigner = $this->user();

            if ($ticket === null || ! $assigner instanceof User) {
                return;
            }

            $targetId = $this->input('assigned_to_user_id');
            if ($targetId === null || $targetId === '') {
                return;
            }

            /** @var User|null $target */
            $target = User::query()->find($targetId);
            if ($target === null) {
                return;
            }

            $service = app(SupportTicketAssignmentService::class);

            if (! $service->targetIsAssignable($ticket, $assigner, $target)) {
                $validator->errors()->add(
                    'assigned_to_user_id',
                    'No puede asignar el ticket a ese usuario según las reglas del Servicio de Salud y el área del ticket.'
                );
            }

            if (
                $target->hasRole('referente')
                && ! $assigner->can('assignReferente', $ticket)
            ) {
                $validator->errors()->add(
                    'assigned_to_user_id',
                    'Solo control de gestión del mismo Servicio de Salud puede asignar un referente.'
                );
            }
        });
    }
}
