<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesIsolation;
use App\Http\Requests\Concerns\ValidatesDietCatalog;
use App\Models\Diet;
use App\Rules\NoHtml;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDietRequest extends FormRequest
{
    use NormalizesIsolation {
        prepareForValidation as normalizeIsolation;
    }
    use ValidatesDietCatalog;

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
    protected function prepareForValidation(): void
    {
        $this->normalizeIsolation();
        $this->normalizeTimeFood();
    }

    public function rules(): array
    {
        return [
            // Obligatorios al registrar (SCRUM-11; TrimStrings deja en null los de solo espacios) y con
            // límite (SCRUM-12: antes, 500 por «Data too long»). Una versión nueva no los cambia: no se validan
            // (así una dieta antigua con un texto más largo se sigue pudiendo editar)
            // Ningún texto libre admite código HTML (NoHtml)
            'habitation' => $this->route('diet') ? ['nullable', new NoHtml] : ['required', 'string', 'max:'.self::HABITATION_MAX, new NoHtml],
            'name_patient' => $this->route('diet') ? ['nullable', new NoHtml] : ['required', 'string', 'max:'.self::NAME_MAX, new NoHtml],
            ...$this->catalogRules(),
            // Habitación compartida: se registra otra dieta activa en la misma habitación solo si se confirma
            'allow_duplicate_room' => ['nullable', 'boolean'],
            'observations' => ['nullable', 'string', new NoHtml],
            'isolation' => $this->isolationRules(),
            'billing_type' => ['required', \Illuminate\Validation\Rule::in(array_keys(\App\Services\BillingService::selectableTypes()))],
            'changes' => ['nullable', 'string', new NoHtml],
        ];
    }

    public function attributes(): array
    {
        return [
            'habitation' => 'habitación',
            'name_patient' => 'nombre del paciente',
            'observations' => 'observaciones',
            'changes' => 'motivo del cambio',
        ];
    }

    public function messages(): array
    {
        return [
            'habitation.required' => 'Escribe la habitación.',
            'habitation.max' => 'Escribe la habitación con :max caracteres como máximo.',
            'name_patient.required' => 'Escribe el nombre del paciente.',
            'name_patient.max' => 'Escribe el nombre del paciente con :max caracteres como máximo.',
            ...$this->catalogMessages(),
            'billing_type.required' => 'Elige el tipo de dieta (facturación).',
            'billing_type.in' => 'Elige un tipo de dieta de la lista.',
            'isolation.in' => 'Elige Sí o No en aislamiento.',
        ];
    }

    /**
     * SCRUM-17: al registrar, una habitación que ya tiene una dieta activa (en la empresa activa) se
     * rechaza, salvo que se confirme que es una habitación compartida (allow_duplicate_room).
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->route('diet') || $validator->errors()->has('habitation') || $this->boolean('allow_duplicate_room')) {
                return;
            }

            $room = (string) $this->input('habitation');
            if ($room !== '' && Diet::active()->where('habitation', $room)->exists()) {
                $validator->errors()->add('habitation', "La habitación {$room} ya tiene una dieta activa. Edítala o, si es una habitación compartida, marca «Registrar de todas formas».");
            }
        }];
    }
}
