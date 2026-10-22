<?php

namespace App\Livewire;

use App\Services\DispatchService;
use App\Support\Pagination;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Despachos del día (nutricionista y despachador): qué despachó cocina y qué se
 * recibió, por fecha y tiempo de comida; aparte, los anulados y los que no llegaron.
 */
#[Layout('layouts.app')]
class DispatchLog extends Component
{
    use WithPagination;

    #[Url(as: 'fecha')]
    public string $date = '';

    #[Url(as: 'servicio', except: '')]
    public string $meal = '';

    #[Url(as: 'estado', except: '')]
    public string $status = ''; // '' | pending | received

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'per_page', except: Pagination::PER_PAGE)]
    public int $perPage = Pagination::PER_PAGE;

    public function mount(): void
    {
        $this->normalize();
    }

    /** Cualquier filtro vuelve a la página 1 */
    public function updated(string $property): void
    {
        if (in_array($property, ['date', 'meal', 'status', 'search', 'perPage'], true)) {
            $this->normalize();
            $this->resetPage();
        }
    }

    public function filterMeal(string $meal): void
    {
        $this->meal = $this->meal === $meal ? '' : $meal;
        $this->normalize();
        $this->resetPage();
    }

    private function normalize(): void
    {
        $today = now()->toDateString();
        $valid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date) && strtotime($this->date) !== false;
        $this->date = ! $valid || $this->date > $today ? $today : $this->date;
        $this->meal = array_key_exists($this->meal, DispatchService::meals()) ? $this->meal : '';
        $this->status = in_array($this->status, ['pending', 'received'], true) ? $this->status : '';
        $this->perPage = Pagination::perPage($this->perPage);
    }

    public function render(DispatchService $dispatches)
    {
        $date = CarbonImmutable::parse($this->date);

        $rows = $dispatches->dayQuery($date, $this->meal, $this->status, trim($this->search))
            ->with(['diet', 'version', 'dispatchedBy', 'receivedBy'])
            ->orderByRoom()
            ->paginate($this->perPage);

        return view('livewire.dispatch-log', [
            'rows' => $rows,
            'day' => $date,
            'summary' => $dispatches->daySummary($date),
            // Anulados por cocina o que no llegaron (no tienen estado de recepción: se ocultan al filtrar por estado)
            'voids' => $this->status === '' ? $dispatches->dayVoids($date, $this->meal, trim($this->search)) : collect(),
            'meals' => DispatchService::meals(),
        ]);
    }
}
