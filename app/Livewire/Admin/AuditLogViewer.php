<?php

namespace App\Livewire\Admin;

use App\Models\AuditLog;
use App\Support\Pagination;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Registro de auditoría (/admin/auditoria, permiso viewAuditLog): solo lectura,
 * filtros por evento, usuario/texto y rango de fechas.
 */
#[Layout('layouts.app')]
class AuditLogViewer extends Component
{
    use WithPagination;

    #[Url(as: 'evento', except: '')]
    public string $event = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'desde', except: '')]
    public string $from = '';

    #[Url(as: 'hasta', except: '')]
    public string $to = '';

    #[Url(as: 'per_page', except: Pagination::PER_PAGE)]
    public int $perPage = Pagination::PER_PAGE;

    public function mount(): void
    {
        $this->authorize('viewAuditLog');
        $this->perPage = Pagination::perPage($this->perPage);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['event', 'search', 'from', 'to', 'perPage'], true)) {
            $this->perPage = Pagination::perPage($this->perPage);
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['event', 'search', 'from', 'to']);
        $this->resetPage();
    }

    private static function date(string $value): ?CarbonImmutable
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? CarbonImmutable::parse($value) : null;
    }

    public function render()
    {
        $search = trim($this->search);
        $from = self::date($this->from);
        $to = self::date($this->to);

        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->when(array_key_exists($this->event, AuditLog::EVENTS), fn ($q) => $q->where('event', $this->event))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from->startOfDay()))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to->endOfDay()))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('description', 'like', "%{$search}%")
                ->orWhere('ip', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->withTrashed()->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))))
            ->latestFirst()
            ->paginate(Pagination::perPage($this->perPage));

        return view('livewire.admin.audit-log-viewer', [
            'logs' => $logs,
            'events' => AuditLog::EVENTS,
        ]);
    }
}
