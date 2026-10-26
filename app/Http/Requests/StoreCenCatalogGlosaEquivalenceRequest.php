<?php

namespace App\Http\Requests;

use App\Enums\Cen\CenCatalogGlosaRuleKind;
use App\Support\Cen\CenCatalogGlosaResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCenCatalogGlosaEquivalenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasRole('admin');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $fieldKey = (string) $this->input('field_key', '');
        $allowedValues = $fieldKey !== '' && in_array($fieldKey, CenCatalogGlosaResolver::fieldKeys(), true)
            ? array_column(CenCatalogGlosaResolver::enumOptionsForField($fieldKey), 'value')
            : [];

        return [
            'field_key' => ['required', 'string', Rule::in(CenCatalogGlosaResolver::fieldKeys())],
            'source_glosa' => ['required', 'string', 'max:512'],
            'target_value' => ['required', 'string', 'max:255', Rule::in($allowedValues)],
            'rule_kind' => ['sometimes', 'string', Rule::enum(CenCatalogGlosaRuleKind::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'target_value.in' => 'El valor destino no corresponde al catálogo del campo seleccionado.',
        ];
    }
}
