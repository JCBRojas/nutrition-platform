<?php

namespace App\Livewire\Admin;

use App\Actions\Fortify\PasswordValidationRules;
use App\Models\Company;
use App\Models\User;
use App\Rules\LocalPhone;
use App\Services\UserService;
use App\Support\Pagination;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app')]
class UsersManager extends Component
{
    use PasswordValidationRules;
    use WithPagination;

    /* ===================== FILTROS ===================== */

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $role = '';

    #[Url(except: 'active')]
    public string $status = 'active'; // active | inactive | trashed | all

    #[Url(as: 'per_page', except: Pagination::PER_PAGE)]
    public int $perPage = Pagination::PER_PAGE;

    /* ===================== FORMULARIO ===================== */

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $is_active = true;

    public bool $whatsapp_opt_in = false;

    public array $roles = [];

    /** Acceso a empresas (NUT-RN-003-05): todas, o las de $companies */
    public bool $all_companies = false;

    public array $companies = [];

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function updating($property): void
    {
        if (in_array($property, ['search', 'role', 'status'], true)) {
            $this->resetPage();
        }
    }

    /* ===================== CRUD ===================== */

    public function create(): void
    {
        $this->authorize('create', User::class);
        $this->resetForm();
        if (count($this->assignable() ?? []) === 1) {
            $this->roles = $this->assignable(); // jefe de nutrición: despachador marcado
        }
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $user = User::findOrFail($id);
        $this->authorize('update', $user);

        $this->resetForm();
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = (string) $user->phone;
        $this->is_active = $user->is_active;
        $this->whatsapp_opt_in = $user->whatsapp_opt_in;
        $this->roles = $user->roles->pluck('name')->all();
        $this->all_companies = $user->all_companies;
        $this->companies = $user->companies->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->showForm = true;
    }

    public function save(UserService $service): void
    {
        $user = $this->editingId ? User::findOrFail($this->editingId) : null;
        $user ? $this->authorize('update', $user) : $this->authorize('create', User::class);
        $this->phone = LocalPhone::normalize($this->phone);

        // SCRUM-25: las reglas de empresas se evalúan con las demás, para mostrar todos los errores a la vez
        $this->withValidator(fn ($validator) => $validator->after(function ($validator) {
            foreach ($this->companyErrors() as $message) {
                $validator->errors()->add('companies', $message);
            }
        }));

        $data = $this->validate($this->rules(), [
            'roles.required' => 'Elige el rol del usuario.',
            'roles.*.in' => 'No puedes asignar ese rol.',
            'email.unique' => 'Ya existe un usuario con este correo (puede estar eliminado: restáuralo).',
            'companies.*.exists' => 'Elige una empresa de la lista.',
        ]);

        if (! $this->assignsCompanies()) {
            // Quien no administra empresas (p. ej. jefe de nutrición) no cambia el acceso; al crear, el
            // usuario nuevo recibe, como empresas específicas, las que puede elegir quien lo crea
            unset($data['all_companies'], $data['companies']);
            if (! $user) {
                $data['all_companies'] = false;
                $data['companies'] = auth()->user()->selectableCompanies()->pluck('companies.id')->all();
            }
        } elseif ($this->isDispatchManager()) {
            // Jefe de despacho: solo sus empresas, nunca «Todas»; conserva las de otros jefes que ya tenga
            $data['all_companies'] = false;
            $others = $user ? $user->companies()->whereNotIn('companies.id', $this->managedCompanyIds())->pluck('companies.id')->all() : [];
            $data['companies'] = [...array_map('intval', $data['companies'] ?? []), ...$others];
        }

        try {
            $user ? $service->update($user, $data) : $service->create($data);
        } catch (ValidationException $e) {
            $this->addError('user', $e->validator->errors()->first());

            return;
        }

        $this->showForm = false;
        $this->resetForm();
        session()->flash('success', $user ? 'Usuario actualizado' : 'Usuario creado');
    }

    public function toggleActive(int $id, UserService $service): void
    {
        $user = User::findOrFail($id);
        $this->authorize('update', $user);
        $this->runGuarded(fn () => $service->toggleActive($user), 'Estado actualizado');
    }

    public function delete(int $id, UserService $service): void
    {
        $user = User::findOrFail($id);
        $this->authorize('delete', $user);
        $this->runGuarded(fn () => $service->delete($user), 'Usuario eliminado');
    }

    public function restore(int $id, UserService $service): void
    {
        $user = User::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $user);
        $this->runGuarded(fn () => $service->restore($user), 'Usuario restaurado');
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'phone' => ['nullable', new LocalPhone],
            'password' => $this->editingId
                ? ['nullable', 'string', Password::default(), 'confirmed']
                : $this->passwordRules(),
            'is_active' => ['boolean'],
            'whatsapp_opt_in' => ['boolean'],
            // Sin ser admin solo se asignan los roles permitidos (jefe de nutrición → despachador) y al menos uno
            'roles' => $this->assignable() === null ? ['array'] : ['required', 'array', 'min:1'],
            'all_companies' => ['boolean'],
            'companies' => ['array'],
            'companies.*' => ['integer', Rule::exists('companies', 'id')],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', UserService::GUARD),
                ...($this->assignable() === null ? [] : [Rule::in($this->assignable())])],
        ];
    }

    /** Ve y asigna el acceso a empresas en el formulario */
    private function assignsCompanies(): bool
    {
        return auth()->user()->can('viewCompanies');
    }

    /** Jefe de despacho (sin ser admin): asigna solo sus empresas y ve solo a los usuarios de ellas */
    /**
     * Reglas de empresas que antes se revisaban después de la validación (aquí y en
     * UserService::syncCompanies, que las mantiene como respaldo).
     */
    private function companyErrors(): array
    {
        if (! $this->assignsCompanies()) {
            return [];
        }

        $selected = array_map('intval', $this->companies);
        if ($this->isDispatchManager()) {
            return $selected === [] || array_diff($selected, $this->managedCompanyIds()) !== []
                ? ['Asigna al menos una de tus empresas.']
                : [];
        }

        $roles = (array) $this->roles;
        $admin = in_array(UserService::ADMIN_ROLE, $roles, true);
        $dispatch = ! $admin && array_intersect([User::DISPATCHER_ROLE, User::DISPATCH_MANAGER_ROLE], $roles) !== [];

        if ($this->all_companies && $dispatch) {
            return ['A los despachadores y jefes de despacho se les asignan empresas específicas, no «Todas».'];
        }
        if (! $this->all_companies && $selected === [] && ! in_array(User::DISPATCH_MANAGER_ROLE, $roles, true)) {
            return [in_array(User::DISPATCHER_ROLE, $roles, true)
                ? 'Asigna al menos una empresa específica al despachador.'
                : 'Asigna al menos una empresa o marca «Todas las empresas».'];
        }

        return [];
    }

    private function isDispatchManager(): bool
    {
        return auth()->user()->isDispatchManager();
    }

    private function managedCompanyIds(): array
    {
        return Company::managedBy(auth()->user())->pluck('id')->all();
    }

    /** Jefe de despacho: «Todas mis empresas» marca (o desmarca) todas sus empresas activas */
    public function toggleOwnCompanies(): void
    {
        abort_unless($this->isDispatchManager(), 403);

        $own = Company::managedBy(auth()->user())->active()->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->companies = array_diff($own, $this->companies) === [] ? [] : $own;
    }

    /** null = puede asignar cualquier rol (admin) */
    private function assignable(): ?array
    {
        return UserService::assignableRoles(auth()->user());
    }

    private function runGuarded(callable $action, string $message): void
    {
        try {
            $action();
            session()->flash('success', $message);
        } catch (ValidationException $e) {
            session()->flash('error', $e->validator->errors()->first());
        }
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'email', 'phone', 'password', 'password_confirmation', 'is_active', 'whatsapp_opt_in', 'roles', 'all_companies', 'companies']);
        $this->resetErrorBag();
    }

    public function render()
    {
        $allRoles = Role::where('guard_name', UserService::GUARD)->orderBy('name')->pluck('name');
        $assignable = $this->assignable();
        if ($assignable !== null) {
            $allRoles = $allRoles->intersect($assignable)->values(); // el jefe de nutrición solo ve y crea despachadores
        }

        // Roles que ve en el listado (incluye los de solo lectura, p. ej. nutricionistas del jefe de despacho)
        $visible = UserService::visibleRoles(auth()->user());
        $filterRoles = $visible === null ? $allRoles : Role::where('guard_name', UserService::GUARD)->whereIn('name', $visible)->orderBy('name')->pluck('name');

        $users = User::query()
            ->with(['roles', 'companies'])
            ->when($this->status === 'trashed', fn ($q) => $q->onlyTrashed())
            ->when($this->status === 'all', fn ($q) => $q->withTrashed())
            ->when($this->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")))
            ->when($filterRoles->contains($this->role), fn ($q) => $q->role($this->role))
            ->when($visible !== null, fn ($q) => $visible === [] ? $q->whereRaw('1 = 0') : $q->role($visible))
            ->when($this->isDispatchManager(), fn ($q) => $q->whereHas('companies', fn ($q) => $q->whereIn('companies.id', $this->managedCompanyIds())))
            ->orderBy('name')
            ->paginate(Pagination::perPage($this->perPage));

        return view('livewire.admin.users-manager', [
            'users' => $users,
            'allRoles' => $allRoles,
            'filterRoles' => $filterRoles,
            'assignsCompanies' => $this->assignsCompanies(),
            'offersAllCompanies' => ! $this->isDispatchManager(),
            'offersOwnCompanies' => $this->isDispatchManager(),
            // Activas, más las desactivadas que el usuario en edición ya tiene (para no quitárselas sin querer)
            'companyOptions' => $this->assignsCompanies()
                ? Company::query()
                    ->when($this->isDispatchManager(), fn ($q) => $q->managedBy(auth()->user()))
                    ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $this->companies ?: [0]))
                    ->orderBy('name')->get(['id', 'name', 'is_active'])
                : collect(),
        ]);
    }
}
