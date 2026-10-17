<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Solicitud de demo enviada desde la página pública */
class DemoRequest extends Model
{
    public const STATUSES = [
        'new' => 'Nueva',
        'contacted' => 'Contactada',
        'discarded' => 'Descartada',
    ];

    protected $fillable = ['name', 'institution', 'position', 'email', 'phone', 'beds', 'message', 'status', 'ip'];

    protected $casts = ['beds' => 'integer'];

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
