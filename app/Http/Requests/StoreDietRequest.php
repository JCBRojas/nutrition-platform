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
            'changes' => 'nullable|string',
        ];
    }
}
