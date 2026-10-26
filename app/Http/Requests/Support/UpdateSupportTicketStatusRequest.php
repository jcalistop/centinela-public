<?php

namespace App\Http\Requests\Support;

use App\Enums\SupportTicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupportTicketStatusRequest extends FormRequest
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
        $statuses = array_map(static fn (SupportTicketStatus $s) => $s->value, SupportTicketStatus::cases());

        return [
            'status' => ['required', Rule::in($statuses)],
            'comment' => ['nullable', 'string', 'max:5000'],
            'is_internal' => ['boolean'],
        ];
    }
}
