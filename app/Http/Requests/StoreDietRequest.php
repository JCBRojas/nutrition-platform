<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesIsolation;
use Illuminate\Foundation\Http\FormRequest;

class StoreDietRequest extends FormRequest
{
    use NormalizesIsolation;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasRole('nutricionista');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'habitation' => 'nullable|string',
            'name_patient' => 'nullable|string',
            'timeFood.*' => 'nullable|string',
            'consistency.*' => 'nullable|string',
            'specifications.*' => 'nullable|string',
            'observations' => 'nullable|string',
            'isolation' => $this->isolationRules(),
            'billing_type' => ['required', \Illuminate\Validation\Rule::in(array_keys(\App\Services\BillingService::selectableTypes()))],
            'changes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'billing_type.required' => 'Elige el tipo de dieta (facturación).',
            'billing_type.in' => 'El tipo de dieta elegido no es válido.',
            'isolation.in' => 'El aislamiento debe ser Sí o No.',
        ];
    }
}
