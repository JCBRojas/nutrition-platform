<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Periodo de plan premium de la institución. Sin un periodo vigente, la institución está en el
 * plan básico (no hay fila para el básico: es gratuito y no vence).
 */
class Subscription extends Model
{
    protected $fillable = ['plan', 'period', 'starts_at', 'ends_at', 'payment_id', 'activated_by', 'notes'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('starts_at', '<=', now())->where('ends_at', '>', now());
    }

    public function payment()
    {
        return $this->belongsTo(SubscriptionPayment::class, 'payment_id');
    }

    public function activatedBy()
    {
        return $this->belongsTo(User::class, 'activated_by')->withTrashed();
    }

    public function periodLabel(): string
    {
        return config("subscriptions.periods.{$this->period}.name", $this->period);
    }
}
