<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Precio vigente del plan premium por periodo (monthly | annual) */
class PlanPrice extends Model
{
    protected $fillable = ['period', 'price', 'updated_by'];

    protected $casts = ['price' => 'decimal:2'];
}
