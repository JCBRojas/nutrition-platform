<?php

namespace App\Livewire;

use App\Models\Diet;
use App\Services\DietHistoryService;
use App\Support\Pagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class DietHistoryViewer extends Component
{
    use WithPagination;

    #[Url(as: 'dieta', except: null)]
    public ?int $dietId = null;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'estado', except: 'all')]
    public string $status = 'all'; // all | active | cancelled | finished

    #[Url(as: 'per_page', except: Pagination::PER_PAGE)]
    public int $perPage = Pagination::PER_PAGE;

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'perPage'], true)) {
            $this->perPage = Pagination::perPage($this->perPage);
            $this->resetPage();
        }
    }

    public function select(int $id): void
    {
        $this->dietId = $id;
    }

    public function render(DietHistoryService $history)
    {
        $diets = Diet::query()
            ->withCount('history')
            ->when($this->status === 'active', fn ($q) => $q->active())
            ->when($this->status === 'cancelled', fn ($q) => $q->where('status', Diet::CANCELLED))
            ->when($this->status === 'finished', fn ($q) => $q->where('status', Diet::FINISHED))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('habitation', 'like', "%{$this->search}%")
                ->orWhere('name_patient', 'like', "%{$this->search}%")))
            ->orderByRoom()
            ->orderByDesc('updated_at') // misma habitación: la dieta más reciente primero
            ->paginate(Pagination::perPage($this->perPage));

        $selected = $this->dietId ? Diet::find($this->dietId) : null;

        return view('livewire.diet-history-viewer', [
            'diets' => $diets,
            'selected' => $selected,
            'timeline' => $selected ? $history->timeline($selected) : collect(),
        ]);
    }
}
