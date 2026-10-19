<?php

namespace App\Livewire;

use App\Models\Diet;
use App\Services\DietDayService;
use App\Services\DietService;
use App\Services\DispatchService;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
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

    /** Pestañas => estados de la dieta (activas incluye las completadas hoy) */
    public const TABS = [
        'active' => Diet::OPEN_STATUSES,
        'cancelled' => [Diet::CANCELLED],
        'finished' => [Diet::FINISHED],
    ];

    #[Url(except: 'active')]
    public string $tab = 'active';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'per_page', except: Pagination::PER_PAGE)]
    public int $perPage = Pagination::PER_PAGE;

    /** Resultado de «Continuar dieta» */
    public ?string $notice = null;

    public bool $noticeError = false;

    /** Modal de cierre: dieta y tipo ('finish' = alta, 'cancel' = cancelar) */
    public ?int $closingId = null;

    public string $closingType = '';

    /** Alta con envío a otro lugar (p. ej. el paciente fue remitido a otra IPS cercana) */
    public bool $transferEnabled = false;

    public string $transferDestination = '';

    public array $transferMeals = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('viewActiveDiets'), 403);

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

    /** «Continuar dieta»: autoriza el despacho de mañana (nutrición y enfermería) */
    public function continueDiet(int $dietId, DietDayService $days): void
    {
        abort_unless(auth()->user()?->can('continueDiets'), 403);

        $diet = Diet::findOrFail($dietId);

        try {
            $days->continueDiet($diet, auth()->id());
            $this->notice = "Dieta de la habitación {$diet->habitation} autorizada para mañana.";
            $this->noticeError = false;
        } catch (ValidationException $e) {
            $this->notice = collect($e->errors())->flatten()->first();
            $this->noticeError = true;
        }
    }

    /* ===================== CANCELAR / DAR DE ALTA ===================== */

    /** Abre la confirmación con lo despachado sin recibir (se cobra) y, en el alta, el envío a otro lugar */
    public function startClose(int $dietId, string $type): void
    {
        abort_unless(auth()->user()?->can('editDiets'), 403);

        $this->resetClose();
        $this->closingId = Diet::active()->findOrFail($dietId)->id;
        $this->closingType = $type === 'cancel' ? 'cancel' : 'finish';
    }

    public function cancelClose(): void
    {
        $this->resetClose();
    }

    public function confirmClose(DietService $diets): void
    {
        abort_unless(auth()->user()?->can('editDiets'), 403);

        $diet = Diet::find($this->closingId);
        if (! $diet) {
            $this->resetClose();

            return;
        }

        $transfer = null;
        if ($this->closingType === 'finish' && $this->transferEnabled) {
            $this->validate([
                'transferDestination' => ['required', 'string', 'max:150'],
                'transferMeals' => ['required', 'array', 'min:1'],
            ], [
                'transferDestination.required' => 'Indica a dónde se debe enviar la comida.',
                'transferMeals.required' => 'Elige al menos un tiempo de comida para enviar.',
            ]);
            $transfer = ['destination' => $this->transferDestination, 'meals' => $this->transferMeals];
        }

        try {
            if ($this->closingType === 'cancel') {
                $diets->cancelDiet($diet, auth()->id());
                $this->notice = "Dieta de la habitación {$diet->habitation} cancelada.";
            } else {
                $diets->finishDiet($diet, auth()->id(), $transfer);
                $this->notice = "Dieta de la habitación {$diet->habitation} finalizada por alta."
                    .($transfer ? ' Cocina verá el envío a '.trim($this->transferDestination).'.' : '');
            }
            $this->noticeError = false;
            $this->resetClose();
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError(str_replace(['transfer.destination', 'transfer.meals'], ['transferDestination', 'transferMeals'], $field), $messages[0]);
            }
        }
    }

    private function resetClose(): void
    {
        $this->reset('closingId', 'closingType', 'transferEnabled', 'transferDestination', 'transferMeals');
        $this->resetErrorBag();
    }

    /** Un despacho recibido o nuevo cambia el estado de la columna Tiempo */
    #[On('dispatch-received')]
    #[On('dispatch-arrived')]
    public function refreshTable(): void {}

    public function render(DispatchService $dispatches, DietDayService $days)
    {
        $search = trim($this->search);

        // La búsqueda aplica a la pestaña abierta (habitación o paciente)
        $diets = Diet::with('currentVersion.updatedBy')
            ->whereIn('status', self::TABS[$this->tab])
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('habitation', 'like', "%{$search}%")
                ->orWhere('name_patient', 'like', "%{$search}%")))
            ->orderByRoom()
            ->latest('updated_at') // misma habitación (dietas cerradas): la más reciente primero
            ->paginate($this->perPage);

        // Indicadores sobre todas las dietas, no solo la página visible
        $counts = Diet::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $pageIds = $diets->getCollection()->pluck('id')->all();
        $activeVersions = Diet::active()->with('currentVersion')->get()->pluck('currentVersion')->filter();

        $closingDiet = $this->closingId ? Diet::active()->with('currentVersion')->find($this->closingId) : null;
        if ($this->closingId && ! $closingDiet) {
            $this->resetClose(); // otro usuario la cerró mientras tanto
        }

        return view('livewire.nutrition-diets', [
            'diets' => $diets,
            'counts' => collect(self::TABS)->map(fn ($statuses) => (int) collect($statuses)->sum(fn ($s) => $counts[$s] ?? 0))->all(),
            'stats' => [
                'isolated' => $activeVersions->where('isolation', 'Sí')->count(),
                'changes' => $activeVersions->reject(fn ($v) => in_array($v->changes, [null, '', 'NA', ...Diet::DAY_CHANGES], true))->count(),
            ],
            'delivery' => $this->tab === 'active' ? $dispatches->statusByDiet($pageIds) : [],
            'dayStatus' => $this->tab === 'active' ? $days->statusByDiet($pageIds) : [],
            'closing' => $closingDiet ? ['diet' => $closingDiet, 'summary' => $dispatches->closingSummary($closingDiet)] : null,
            'specLabels' => config('specifications.labels'),
            'consistencyLabels' => config('consistency.labels'),
            'foodLabels' => config('timeFood.labels'),
        ]);
    }
}
