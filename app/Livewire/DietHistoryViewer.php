<?php

namespace App\Livewire;

use App\Models\Diet;
use App\Services\DietHistoryService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class DietHistoryViewer extends Component
{
    #[Url(as: 'dieta', except: null)]
    public ?int $dietId = null;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'estado', except: 'all')]
    public string $status = 'all'; // all | active | cancelled | finished

    public function select(int $id): void
    {
        $this->dietId = $id;
    }

    public function render(DietHistoryService $history)
    {
        $diets = Diet::query()
            ->withCount('history')
            ->when($this->status === 'active', fn ($q) => $q->where('status', Diet::ACTIVE))
            ->when($this->status === 'cancelled', fn ($q) => $q->where('status', Diet::CANCELLED))
            ->when($this->status === 'finished', fn ($q) => $q->where('status', Diet::FINISHED))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('habitation', 'like', "%{$this->search}%")
                ->orWhere('name_patient', 'like', "%{$this->search}%")))
            ->orderByDesc('updated_at')
            ->limit(40)
            ->get();

        $selected = $this->dietId ? Diet::find($this->dietId) : null;

        return view('livewire.diet-history-viewer', [
            'diets' => $diets,
            'selected' => $selected,
            'timeline' => $selected ? $history->timeline($selected) : collect(),
        ]);
    }
}
