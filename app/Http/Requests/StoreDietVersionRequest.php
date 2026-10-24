<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesIsolation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDietVersionRequest extends FormRequest
{
    use NormalizesIsolation;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('editDiets'); // por permiso, no por rol
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'timeFood.*' => 'nullable|string',
            'consistency.*' => 'nullable|string',
            'specifications.*' => 'nullable|string',
            'observations' => 'nullable|string',
            'isolation' => $this->isolationRules(),
            'changes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'isolation.in' => 'El aislamiento debe ser Sí o No.',
        ];
    }
}
