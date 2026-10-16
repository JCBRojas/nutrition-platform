<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DietDispatch extends Model
{
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
