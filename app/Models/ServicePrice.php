<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServicePrice extends Model
{
    protected $fillable = [
        'billing_type',
        'service',
        'price',
        'valid_from',
        'created_by',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'valid_from' => 'date',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
