<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Diet extends Model
{
    use HasFactory;
    protected $fillable = [
        'habitation',
        'name_patient',
        'status',
        'confirmed',
    ];

    protected $casts = [
        'status' => 'boolean',
        'confirmed' => 'boolean',
    ];

    public function history()
    {
        return $this->hasMany(DietHistory::class, 'diet_id');
    }

    public function currentVersion()
    {
        return $this->hasOne(DietHistory::class, 'diet_id')->latestOfMany('version');
    }

}
