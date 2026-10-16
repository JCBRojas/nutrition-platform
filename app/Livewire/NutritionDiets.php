<?php

namespace App\Livewire;

use App\Models\Diet;
use App\Services\DispatchService;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Panel del nutricionista: indicadores, pestañas y tablas de dietas (activas,
 * canceladas, finalizadas). Se refresca solo (ver la vista: se pausa mientras hay
 * un modal abierto) y la búsqueda filtra en tiempo real.
 */
class NutritionDiets extends Component
{
    use WithPagination;

    /** Pestañas => estado de la dieta */
    public const TABS = [
        'active' => Diet::ACTIVE,
        'cancelled' => Diet::CANCELLED,
        'finished' => Diet::FINISHED,
    ];

    #[Url(except: 'active')]
    public string $tab = 'active';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'per_page', except: Pagination::PER_PAGE)]
    public int $perPage = Pagination::PER_PAGE;

    public function mount(): void
    {
        $this->tab = array_key_exists($this->tab, self::TABS) ? $this->tab : 'active';
        $this->perPage = Pagination::perPage($this->perPage);
    }

    public function setTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, self::TABS) ? $tab : 'active';
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->perPage = Pagination::perPage($this->perPage);
        $this->resetPage();
    }

    /** Un despacho recibido o nuevo cambia el estado de la columna Tiempo */
    #[On('dispatch-received')]
    #[On('dispatch-arrived')]
    public function refreshTable(): void {}

    public function render(DispatchService $dispatches)
    {
        $search = trim($this->search);

        // La búsqueda aplica a la pestaña abierta (habitación o paciente)
        $diets = Diet::with('currentVersion.updatedBy')
            ->where('status', self::TABS[$this->tab])
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('habitation', 'like', "%{$search}%")
                ->orWhere('name_patient', 'like', "%{$search}%")))
            ->orderByRoom()
            ->latest('updated_at') // misma habitación (dietas cerradas): la más reciente primero
            ->paginate($this->perPage);

        // Indicadores sobre todas las dietas, no solo la página visible
        $counts = Diet::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $activeVersions = Diet::active()->with('currentVersion')->get()->pluck('currentVersion')->filter();

        return view('livewire.nutrition-diets', [
            'diets' => $diets,
            'counts' => collect(self::TABS)->map(fn ($status) => (int) ($counts[$status] ?? 0))->all(),
            'stats' => [
                'isolated' => $activeVersions->where('isolation', 'Sí')->count(),
                'changes' => $activeVersions->reject(fn ($v) => in_array($v->changes, [null, '', 'NA'], true))->count(),
            ],
            'delivery' => $this->tab === 'active' ? $dispatches->statusByDiet($diets->getCollection()->pluck('id')->all()) : [],
            'specLabels' => config('specifications.labels'),
            'consistencyLabels' => config('consistency.labels'),
            'foodLabels' => config('timeFood.labels'),
        ]);
    }
}
