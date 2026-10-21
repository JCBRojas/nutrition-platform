<?php

namespace App\Models\Scopes;

use App\Models\Diet;
use App\Models\ServicePrice;
use App\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filtra por la empresa activa (NUT-RN-003-11, -12): con un usuario autenticado, ninguna
 * consulta de dietas, despachos, días ni precios devuelve datos de otra empresa. Sin usuario
 * (consola, colas, programador) no filtra.
 *
 * Diet y ServicePrice filtran por company_id; los modelos que cuelgan de una dieta, por diet_id.
 */
class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $companyId = app(CurrentCompany::class)->id();
        if ($companyId === null) {
            return;
        }

        $model instanceof Diet || $model instanceof ServicePrice
            ? $builder->where($model->qualifyColumn('company_id'), $companyId)
            : $builder->whereIn($model->qualifyColumn('diet_id'), Diet::withoutGlobalScope(self::class)->select('id')->where('company_id', $companyId));
    }
}
