<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use App\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Precio por bandeja de un tipo de dieta × servicio, por empresa (NUT-RN-003-14).
 * Con un usuario autenticado solo se consultan los de la empresa activa (CompanyScope).
 */
class ServicePrice extends Model
{
    protected $fillable = [
        'company_id',
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

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (ServicePrice $price) {
            $price->company_id ??= static::defaultCompanyId();
        });

        static::updating(function (ServicePrice $price) {
            if ($price->isDirty('company_id') && $price->getOriginal('company_id') !== null) {
                throw new LogicException('La empresa de un precio no se cambia.');
            }
        });
    }

    /** Empresa activa o, sin usuario (consola), la predeterminada */
    public static function defaultCompanyId(): ?int
    {
        return app(CurrentCompany::class)->id() ?: Company::default()?->id;
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
