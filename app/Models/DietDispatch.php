<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use App\Services\Reports\DietReportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DietDispatch extends Model
{
    /** Solo los de dietas de la empresa activa (CompanyScope) */
    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    protected $fillable = [
        'diet_id',
        'diet_history_id',
        'service_date',
        'meal',
        'dispatched_by',
        'dispatched_at',
        'received_by',
        'received_at',
    ];

    protected $casts = [
        'service_date' => 'date',
        'dispatched_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function scopePendingReceipt(Builder $query): Builder
    {
        return $query->whereNull('received_at');
    }

    public function scopeReceived(Builder $query): Builder
    {
        return $query->whereNotNull('received_at');
    }

    /** Habitación ascendente (orden natural, como Diet::orderByRoom) y luego tiempo de comida en orden cronológico */
    public function scopeOrderByRoom(Builder $query): Builder
    {
        $room = fn (string $expr) => Diet::selectRaw($expr)->whereColumn('diets.id', 'diet_dispatches.diet_id');
        $meals = collect(DietReportService::MEALS)->map(fn ($m, $i) => "WHEN '{$m}' THEN {$i}")->implode(' ');

        return $query->orderBy($room('LENGTH(habitation)'))
            ->orderBy($room('habitation'))
            ->orderByRaw("CASE meal {$meals} ELSE 99 END");
    }

    public function isReceived(): bool
    {
        return $this->received_at !== null;
    }

    public function diet()
    {
        return $this->belongsTo(Diet::class);
    }

    public function version()
    {
        return $this->belongsTo(DietHistory::class, 'diet_history_id');
    }

    public function dispatchedBy()
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
