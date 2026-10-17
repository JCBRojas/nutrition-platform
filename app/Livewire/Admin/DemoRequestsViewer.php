<?php

namespace App\Livewire\Admin;

use App\Models\DemoRequest;
use App\Support\Pagination;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Solicitudes de demo del formulario público (/admin/solicitudes-demo, permiso viewDemoRequests) */
#[Layout('layouts.app')]
class DemoRequestsViewer extends Component
{
    use WithPagination;

    #[Url(as: 'estado', except: '')]
    public string $status = '';

    #[Url(as: 'per_page', except: Pagination::PER_PAGE)]
    public int $perPage = Pagination::PER_PAGE;

    public function mount(): void
    {
        $this->authorize('viewDemoRequests');
        $this->perPage = Pagination::perPage($this->perPage);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'perPage'], true)) {
            $this->perPage = Pagination::perPage($this->perPage);
            $this->resetPage();
        }
    }

    public function setStatus(int $id, string $status): void
    {
        $this->authorize('viewDemoRequests');
        validator(['status' => $status], ['status' => [Rule::in(array_keys(DemoRequest::STATUSES))]])->validate();

        DemoRequest::findOrFail($id)->update(['status' => $status]);
    }

    public function render()
    {
        return view('livewire.admin.demo-requests-viewer', [
            'requests' => DemoRequest::query()
                ->when(array_key_exists($this->status, DemoRequest::STATUSES), fn ($q) => $q->where('status', $this->status))
                ->latest()
                ->paginate(Pagination::perPage($this->perPage)),
            'statuses' => DemoRequest::STATUSES,
            'newCount' => DemoRequest::where('status', 'new')->count(),
        ]);
    }
}
