<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\Scopes\CompanyScope;
use App\Models\User;
use App\Rules\LocalPhone;
use App\Rules\Nit;
use App\Services\CompanyService;
use App\Support\Pagination;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Empresas atendidas por el despacho (/empresas, NUT-RN-003-01 a -03, -20 a -22).
 * Ver: viewCompanies o manageCompanies. Crear, editar y desactivar: manageCompanies.
 * El admin ve todas y elige el jefe de despacho de cada una; el jefe de despacho solo ve
 * y administra las suyas, y las que crea quedan a su nombre. Los demás (p. ej. el despachador)
 * solo consultan las empresas que tienen asignadas (NUT-RN-003-06).
 */
#[Layout('layouts.app')]
class CompaniesManager extends Component
{
    use WithPagination;

    /* ===================== FILTROS ===================== */

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'estado', except: 'active')]
    public string $status = 'active'; // active | inactive | all

    #[Url(as: 'per_page', except: Pagination::PER_PAGE)]
    public int $perPage = Pagination::PER_PAGE;

    /* ===================== FORMULARIO ===================== */

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $nit = '';

    public string $contact_name = '';

    public string $contact_phone = '';

    public string $contact_email = '';

    /** Jefe de despacho de la empresa (lo elige el admin) */
    public ?int $manager_id = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->canAny(['viewCompanies', 'manageCompanies']), 403);
        $this->perPage = Pagination::perPage($this->perPage);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'perPage'], true)) {
            $this->perPage = Pagination::perPage($this->perPage);
            $this->resetPage();
        }
    }

    /* ===================== CRUD ===================== */

    public function create(): void
    {
        $this->authorize('manageCompanies');
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('manageCompanies');
        $company = $this->findOwn($id);

        $this->resetForm();
        $this->editingId = $company->id;
        $this->name = $company->name;
        $this->nit = (string) $company->nit;
        $this->contact_name = (string) $company->contact_name;
        $this->contact_phone = (string) $company->contact_phone;
        $this->contact_email = (string) $company->contact_email;
        $this->manager_id = $company->manager_id;
        $this->showForm = true;
    }

    public function save(CompanyService $service): void
    {
        $this->authorize('manageCompanies');
        $company = $this->editingId ? $this->findOwn($this->editingId) : null;

        $this->name = trim($this->name);
        $this->nit = Nit::normalize($this->nit);
        $this->contact_phone = LocalPhone::normalize($this->contact_phone);

        $data = $this->validate($this->rules(), [
            'name.unique' => 'Ya existe una empresa con este nombre.',
            'nit.unique' => 'Ya existe una empresa con este NIT.',
        ], [
            'name' => 'nombre',
            'nit' => 'NIT',
            'contact_name' => 'nombre del contacto',
            'contact_phone' => 'teléfono del contacto',
            'contact_email' => 'correo del contacto',
            'manager_id' => 'jefe de despacho',
        ]);

        // El jefe de despacho administra lo que crea; al editar no cambia el jefe de la empresa
        if ($this->isManager()) {
            unset($data['manager_id']);
            if (! $company) {
                $data['manager_id'] = auth()->id();
            }
        }

        $company ? $service->update($company, $data) : $service->create($data);

        $this->showForm = false;
        $this->resetForm();
        session()->flash('success', $company ? 'Empresa actualizada' : 'Empresa creada');
    }

    public function toggleActive(int $id, CompanyService $service): void
    {
        $this->authorize('manageCompanies');

        try {
            $company = $service->toggleActive($this->findOwn($id));
            session()->flash('success', $company->is_active ? 'Empresa reactivada' : 'Empresa desactivada');
        } catch (ValidationException $e) {
            session()->flash('error', $e->validator->errors()->first());
        }
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    /* ===================== HELPERS ===================== */

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('companies', 'name')->ignore($this->editingId)],
            'nit' => ['required', 'string', new Nit, Rule::unique('companies', 'nit')->ignore($this->editingId)],
            'contact_name' => ['required', 'string', 'max:120'],
            'contact_phone' => ['required', new LocalPhone],
            'contact_email' => ['required', 'email', 'max:160'],
            'manager_id' => $this->isManager() ? ['nullable'] : ['required', 'integer', Rule::in($this->managerOptions()->pluck('id'))],
        ];
    }

    /** Jefe de despacho (sin ser admin): solo sus empresas */
    private function isManager(): bool
    {
        return auth()->user()->isDispatchManager();
    }

    /** Empresa por ID; el jefe de despacho solo las suyas (403 las de otro jefe) */
    private function findOwn(int $id): Company
    {
        $company = Company::findOrFail($id);
        abort_if($this->isManager() && $company->manager_id !== auth()->id(), 403);

        return $company;
    }

    /** Jefes de despacho activos que el admin puede asociar a una empresa */
    private function managerOptions()
    {
        return User::role(User::DISPATCH_MANAGER_ROLE)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']);
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'nit', 'contact_name', 'contact_phone', 'contact_email', 'manager_id']);
        $this->resetErrorBag();
    }

    public function render()
    {
        $companies = Company::query()
            ->with('manager')
            // Conteo de cada empresa, no solo de la activa (CompanyScope filtraría las demás a 0)
            ->withCount(['diets' => fn ($q) => $q->withoutGlobalScope(CompanyScope::class)])
            ->when($this->isManager(), fn ($q) => $q->managedBy(auth()->user()))
            ->when(! $this->isManager() && ! auth()->user()->hasAllCompanies(),
                fn ($q) => $q->whereIn('companies.id', auth()->user()->accessibleCompanies()->select('companies.id')))
            ->when($this->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('nit', 'like', "%{$this->search}%")
                ->orWhere('contact_name', 'like', "%{$this->search}%")))
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate(Pagination::perPage($this->perPage));

        return view('livewire.companies-manager', [
            'companies' => $companies,
            'canManage' => auth()->user()->can('manageCompanies'),
            'isManager' => $this->isManager(),
            'managers' => $this->isManager() ? collect() : $this->managerOptions(),
        ]);
    }
}
