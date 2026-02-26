<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Paciente extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $casts = [
        'timeFood' => 'array',
        'specifications' => 'array',
        'changes' => 'array',
    ];

    public function getTimeFoodArrayAttribute()
    {
        return is_array($this->timeFood)
            ? $this->timeFood
            : json_decode($this->timeFood, true) ?? [];
    }

    public function getConsistencyArrayAttribute()
    {
        return is_array($this->consistency)
            ? $this->consistency
            : json_decode($this->consistency, true) ?? [];
    }

    public function getSpecificationsArrayAttribute()
    {
        return is_array($this->specifications)
            ? $this->specifications
            : json_decode($this->specifications, true) ?? [];
    }
}
