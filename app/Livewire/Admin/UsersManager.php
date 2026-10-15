<?php

namespace App\Livewire\Admin;

use App\Actions\Fortify\PasswordValidationRules;
use App\Models\User;
use App\Services\UserService;
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
        $this->showForm = true;
    }

    public function save(UserService $service): void
    {
        $user = $this->editingId ? User::findOrFail($this->editingId) : null;
        $user ? $this->authorize('update', $user) : $this->authorize('create', User::class);

        $data = $this->validate($this->rules(), [
            'email.unique' => 'Ya existe un usuario con este correo (puede estar eliminado: restáuralo).',
            'phone.regex' => 'Usa formato internacional, p. ej. +573001234567.',
        ]);

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
            'phone' => ['nullable', 'regex:/^\+[1-9]\d{7,14}$/'],
            'password' => $this->editingId
                ? ['nullable', 'string', Password::default(), 'confirmed']
                : $this->passwordRules(),
            'is_active' => ['boolean'],
            'whatsapp_opt_in' => ['boolean'],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ];
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
        $this->reset(['editingId', 'name', 'email', 'phone', 'password', 'password_confirmation', 'is_active', 'whatsapp_opt_in', 'roles']);
        $this->resetErrorBag();
    }

    public function render()
    {
        $allRoles = Role::orderBy('name')->pluck('name');

        $users = User::query()
            ->with('roles')
            ->when($this->status === 'trashed', fn ($q) => $q->onlyTrashed())
            ->when($this->status === 'all', fn ($q) => $q->withTrashed())
            ->when($this->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")))
            ->when($allRoles->contains($this->role), fn ($q) => $q->role($this->role))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.admin.users-manager', [
            'users' => $users,
            'allRoles' => $allRoles,
        ]);
    }
}
