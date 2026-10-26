<?php

namespace App\Http\Requests;

use App\Support\Cen\CenCatalogGlosaResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromoteCenCatalogGlosaEquivalenceRequest extends FormRequest
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
        $issue = $this->route('issue');
        $fieldKey = is_object($issue) && is_array($issue->context ?? null)
            ? (string) ($issue->context['field'] ?? '')
            : '';

        $allowedValues = $fieldKey !== '' && in_array($fieldKey, CenCatalogGlosaResolver::fieldKeys(), true)
            ? array_column(CenCatalogGlosaResolver::enumOptionsForField($fieldKey), 'value')
            : [];

        return [
            'target_value' => ['required', 'string', 'max:255', Rule::in($allowedValues)],
        ];
    }
}
