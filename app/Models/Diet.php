<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use App\Support\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class Diet extends Model
{
    use HasFactory;

    /**
     * Estados de diets.status (columna tinyint; no usar cast boolean: 2 se leería como true).
     */
    public const CANCELLED = 0;

    public const ACTIVE = 1;

    public const FINISHED = 2; // alta: el paciente salió de la institución

    public const COMPLETED = 3; // se recibieron todos los tiempos del día; sigue en curso

    /** Dietas en curso (paciente hospitalizado): activas y completadas */
    public const OPEN_STATUSES = [self::ACTIVE, self::COMPLETED];

    /** Valores de diet_histories.changes que cierran una dieta */
    public const CLOSING_CHANGES = ['cancelada', 'finalizada'];

    /** Versiones que registra el sistema por día (copia de la anterior, sin cambio clínico) */
    public const DAY_CHANGES = ['completada', 'continuada'];

    public const STATUS_LABELS = [
        self::ACTIVE => 'Activa',
        self::COMPLETED => 'Completada',
        self::CANCELLED => 'Cancelada',
        self::FINISHED => 'Finalizada',
    ];

    protected $fillable = [
        'company_id',
        'habitation',
        'name_patient',
        'status',
        'confirmed',
        'transfer_destination',
        'transfer_meals',
        'transfer_date',
    ];

    protected $casts = [
        'status' => 'integer',
        'confirmed' => 'boolean',
        'transfer_meals' => 'array',
        'transfer_date' => 'date',
    ];

    /**
     * Sin empresa indicada, la dieta queda en la activa del usuario o en la predeterminada
     * (NUT-RN-003-07, -18). La empresa no cambia después de crearla.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (Diet $diet) {
            $diet->company_id ??= app(CurrentCompany::class)->id() ?: Company::default()?->id;
        });

        static::updating(function (Diet $diet) {
            if ($diet->isDirty('company_id') && $diet->getOriginal('company_id') !== null) {
                throw new LogicException('La empresa de una dieta no se cambia.');
            }
        });
    }

    /**
     * Dieta por ID para una acción del usuario: 404 si no existe, 403 si es de otra empresa
     * (NUT-RN-003-12). Las consultas normales ya filtran con CompanyScope.
     */
    public static function findAccessible(int|string $id): self
    {
        $diet = static::withoutGlobalScope(CompanyScope::class)->findOrFail($id);
        $current = app(CurrentCompany::class)->id();
        abort_if($current !== null && $diet->company_id !== $current, 403);

        return $diet;
    }

    /** Rutas con {diet}: misma regla que findAccessible (403 en vez de 404 para otra empresa) */
    public function resolveRouteBinding($value, $field = null)
    {
        return $field === null || $field === $this->getRouteKeyName()
            ? static::findAccessible($value)
            : parent::resolveRouteBinding($value, $field);
    }

    /** En curso: activas y completadas (no canceladas ni finalizadas) */
    public function scopeActive($query)
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    /** Dadas de alta con comida por enviar a otro lugar ese día (cocina aún las despacha) */
    public function scopeTransferOn($query, $date = null)
    {
        return $query->where('status', self::FINISHED)
            ->whereNotNull('transfer_destination')
            ->whereDate('transfer_date', CarbonImmutable::parse($date ?? now())->toDateString());
    }

    /**
     * Habitación ascendente en orden natural (101, 102, …, 1001): primero por longitud,
     * luego alfabético. Igual en MySQL y SQLite.
     */
    public function scopeOrderByRoom($query)
    {
        return $query->orderByRaw('LENGTH(habitation)')->orderBy('habitation');
    }

    /** En curso (activa o completada): se puede editar, despachar, cancelar o dar de alta */
    public function isActive(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::COMPLETED;
    }

    /** Tiempos que se envían a otro lugar ese día (alta con traslado), o [] */
    public function transferMealsOn($date = null): array
    {
        if ($this->status !== self::FINISHED || ! $this->transfer_destination || ! $this->transfer_date
            || ! $this->transfer_date->isSameDay(CarbonImmutable::parse($date ?? now()))) {
            return [];
        }

        return array_values($this->transfer_meals ?? []);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? 'Desconocido';
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function history()
    {
        return $this->hasMany(DietHistory::class, 'diet_id');
    }

    public function dispatches()
    {
        return $this->hasMany(DietDispatch::class);
    }

    public function days()
    {
        return $this->hasMany(DietDay::class);
    }

    public function currentVersion()
    {
        return $this->hasOne(DietHistory::class, 'diet_id')->latestOfMany('version');
    }

    /**
     * Datos que el botón «Editar dieta» lleva en el HTML (openEditModal): solo campos de la dieta
     * y de su versión actual. Nunca el modelo completo: con la relación updatedBy cargada
     * exponía correo, teléfono, 2FA y aceptación de la política de quien editó (SCRUM-18).
     */
    public function editPayload(array $lockedMeals = []): array
    {
        $v = $this->currentVersion;

        return [
            // Tiempos que nutrición ya recibió hoy: no se pueden quitar al editar
            'locked_meals' => array_values($lockedMeals),
            'habitation' => $this->habitation,
            'name_patient' => $this->name_patient,
            'current_version' => [
                'timeFood' => $v?->timeFood ?? [],
                'consistency' => $v?->consistency ?? [],
                'specifications' => $v?->specifications ?? [],
                'observations' => $v?->observations,
                // getAttribute: dentro de un modelo, ->changes es la propiedad protegida de Eloquent
                // (atributos modificados, siempre vacía aquí) y no la columna
                'changes' => $v?->getAttribute('changes'),
                'isolation' => $v?->isolation,
            ],
        ];
    }
}
