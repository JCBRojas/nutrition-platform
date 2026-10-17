<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pago manual del plan premium reportado con su comprobante. El admin lo aprueba (activa o
 * extiende el premium) o lo rechaza con un motivo.
 */
class SubscriptionPayment extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const STATUSES = [
        self::PENDING => 'Pendiente de revisión',
        self::APPROVED => 'Aprobado',
        self::REJECTED => 'Rechazado',
    ];

    protected $fillable = ['period', 'amount', 'method', 'reference', 'receipt_path', 'status', 'submitted_by', 'reviewed_by', 'reviewed_at', 'review_notes'];

    protected $casts = [
        'amount' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by')->withTrashed();
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    public function subscription()
    {
        return $this->hasOne(Subscription::class, 'payment_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function methodLabel(): string
    {
        return config("subscriptions.payment_methods.{$this->method}.name", $this->method);
    }

    public function periodLabel(): string
    {
        return config("subscriptions.periods.{$this->period}.name", $this->period);
    }
}
