<?php

namespace App\Livewire\Admin;

use App\Models\EmailDomain;
use App\Services\EmailDomainService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Dominios de correo autorizados para iniciar sesión (NUT-RN-002, /admin/dominios, permiso
 * manageEmailDomains). Una sola lista para la plataforma; vacía = sin restricción.
 */
#[Layout('layouts.app')]
class EmailDomainsManager extends Component
{
    public string $domain = '';

    /** Dominio que se va a eliminar (modal abierto) */
    public ?int $deletingId = null;

    public function mount(): void
    {
        $this->authorize('manageEmailDomains');
    }

    public function add(EmailDomainService $service): void
    {
        $this->authorize('manageEmailDomains');
        $this->domain = EmailDomain::normalize($this->domain);
        $this->validate([
            'domain' => ['required', 'string', 'max:253', 'regex:'.EmailDomain::PATTERN, Rule::unique('email_domains', 'domain')],
        ], [
            'domain.required' => 'Escribe el dominio, p. ej. hospitalsanjose.org.',
            'domain.regex' => 'Escribe solo el dominio, sin usuario: p. ej. hospitalsanjose.org.',
            'domain.unique' => 'Ese dominio ya está en la lista.',
        ]);

        // Si la lista estaba vacía y el dominio no es el de un admin, el servicio lo rechaza (campo «domain»)
        $model = $service->create($this->domain);
        $this->reset('domain');
        session()->flash('success', "Dominio {$model->domain} autorizado.");
    }

    public function toggle(int $id, EmailDomainService $service): void
    {
        $this->authorize('manageEmailDomains');
        $this->runGuarded(function () use ($id, $service) {
            $model = $service->toggleActive(EmailDomain::findOrFail($id));

            return "Dominio {$model->domain} ".($model->is_active ? 'activado.' : 'desactivado: sus usuarios ya no pueden ingresar.');
        });
    }

    public function startDelete(int $id): void
    {
        $this->authorize('manageEmailDomains');
        $this->deletingId = EmailDomain::findOrFail($id)->id;
        $this->resetValidation();
    }

    public function cancelDelete(): void
    {
        $this->reset('deletingId');
        $this->resetValidation();
    }

    public function confirmDelete(EmailDomainService $service): void
    {
        $this->authorize('manageEmailDomains');
        $model = EmailDomain::findOrFail($this->deletingId);
        $this->reset('deletingId');
        $this->runGuarded(function () use ($model, $service) {
            $service->delete($model);

            return "Dominio {$model->domain} eliminado.";
        });
    }

    /** Las reglas del servicio (último admin con acceso) se muestran como aviso, no en un campo */
    private function runGuarded(callable $action): void
    {
        try {
            session()->flash('success', $action());
        } catch (ValidationException $e) {
            session()->flash('error', $e->validator->errors()->first());
        }
    }

    public function render()
    {
        $domains = EmailDomain::orderBy('domain')->get()
            ->each(fn (EmailDomain $d) => $d->users_count = $d->users()->count());

        return view('livewire.admin.email-domains-manager', [
            'domains' => $domains,
            'deleting' => $this->deletingId ? $domains->firstWhere('id', $this->deletingId) : null,
        ]);
    }
}
