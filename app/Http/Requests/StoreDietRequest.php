<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesIsolation;
use App\Http\Requests\Concerns\ValidatesDietCatalog;
use App\Models\Diet;
use App\Models\DietDispatch;
use App\Rules\NoHtml;
use Illuminate\Contracts\Validation\ValidationRule;
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
     * @return array<string, ValidationRule|array<mixed>|string>
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
            if ($this->route('diet')) {
                $this->keepReceivedMeals($validator);

                return;
            }

            if ($validator->errors()->has('habitation') || $this->boolean('allow_duplicate_room')) {
                return;
            }

            $room = (string) $this->input('habitation');
            if ($room !== '' && Diet::active()->where('habitation', $room)->exists()) {
                $validator->errors()->add('habitation', "La habitación {$room} ya tiene una dieta activa. Edítala o, si es una habitación compartida, marca «Registrar de todas formas».");
            }
        }];
    }

    /**
     * Al versionar, los tiempos que nutrición ya recibió hoy no se pueden quitar: ya se sirvieron
     * (y se facturan). P. ej. con el almuerzo recibido, la edición debe conservar el almuerzo.
     */
    private function keepReceivedMeals(Validator $validator): void
    {
        if ($validator->errors()->has('timeFood') || $validator->errors()->has('timeFood.*')) {
            return;
        }

        // La web enlaza el modelo; la API v1 recibe el ID (busca la dieta en el controlador)
        $diet = $this->route('diet');
        $dietId = $diet instanceof Diet ? $diet->id : (int) $diet;
        $plain = fn ($meal) => str_replace('_', '', (string) $meal);
        $selected = array_map($plain, (array) $this->input('timeFood'));
        $removed = DietDispatch::received()
            ->where('diet_id', $dietId)
            ->whereDate('service_date', now()->toDateString())
            ->pluck('meal')
            ->reject(fn ($meal) => in_array($plain($meal), $selected, true))
            ->map(fn ($meal) => config("timeFood.labels.{$meal}", $meal));

        if ($removed->isNotEmpty()) {
            $validator->errors()->add('timeFood', 'No puedes quitar '.$removed->implode(', ').': nutrición ya confirmó su recepción hoy.');
        }
    }
}
