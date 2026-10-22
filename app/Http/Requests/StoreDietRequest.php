<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesIsolation;
use Illuminate\Foundation\Http\FormRequest;

class StoreDietRequest extends FormRequest
{
    use NormalizesIsolation;

    /** Longitud máxima de la habitación y del nombre del paciente (también maxlength en el formulario) */
    public const HABITATION_MAX = 4;

    public const NAME_MAX = 150;

    /**
     * Determine if the user is authorized to make this request.
     */
    /**
     * Por permiso, no por rol (cualquier rol puede recibirlos desde /admin/roles):
     * registrar (POST /dietas) → createDiets; nueva versión (PUT /dietas/{diet}/version) → editDiets.
     */
    public function authorize(): bool
    {
        return $this->user()->can($this->route('diet') ? 'editDiets' : 'createDiets');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Obligatorios al registrar (SCRUM-11; TrimStrings deja en null los de solo espacios) y con
            // límite (SCRUM-12: antes, 500 por «Data too long»). Una versión nueva no los cambia: no se validan
            // (así una dieta antigua con un texto más largo se sigue pudiendo editar)
            'habitation' => $this->route('diet') ? 'nullable' : ['required', 'string', 'max:'.self::HABITATION_MAX],
            'name_patient' => $this->route('diet') ? 'nullable' : ['required', 'string', 'max:'.self::NAME_MAX],
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
            'habitation.required' => 'Escribe la habitación.',
            'habitation.max' => 'La habitación no puede tener más de :max caracteres.',
            'name_patient.required' => 'Escribe el nombre del paciente.',
            'name_patient.max' => 'El nombre del paciente no puede tener más de :max caracteres.',
            'billing_type.required' => 'Elige el tipo de dieta (facturación).',
            'billing_type.in' => 'El tipo de dieta elegido no es válido.',
            'isolation.in' => 'El aislamiento debe ser Sí o No.',
        ];
    }
}
