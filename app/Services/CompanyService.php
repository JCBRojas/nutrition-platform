<?php

namespace App\Services;

use App\Models\Company;
use App\Rules\Nit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Empresas atendidas por el despacho (NUT-RN-003-01 a -04). Nunca se borran: se desactivan.
 */
class CompanyService
{
    public function create(array $data): Company
    {
        return DB::transaction(function () use ($data) {
            $company = Company::create($this->attributes($data));
            AuditLogger::log('company_created', "Creó la empresa {$company->name}", $company, ['nit' => $company->nit]);

            return $company;
        });
    }

    public function update(Company $company, array $data): Company
    {
        return DB::transaction(function () use ($company, $data) {
            $company->fill($this->attributes($data));
            $changed = array_keys($company->getDirty());
            $company->save();

            AuditLogger::log('company_updated', "Modificó la empresa {$company->name}", $company, ['fields' => $changed]);

            return $company;
        });
    }

    /** Desactiva o reactiva. La desactivada sale de los selectores pero conserva su historial. */
    public function toggleActive(Company $company): Company
    {
        if ($company->is_active && $company->is_default) {
            throw ValidationException::withMessages(['company' => 'La empresa predeterminada no se puede desactivar.']);
        }

        $company->update(['is_active' => ! $company->is_active]);

        $company->is_active
            ? AuditLogger::log('company_updated', "Reactivó la empresa {$company->name}", $company, ['fields' => ['is_active']])
            : AuditLogger::log('company_deactivated', "Desactivó la empresa {$company->name}", $company);

        return $company;
    }

    private function attributes(array $data): array
    {
        return [
            'name' => trim($data['name']),
            'nit' => Nit::normalize($data['nit']),
            'contact_name' => trim($data['contact_name']),
            'contact_phone' => $data['contact_phone'],
            'contact_email' => mb_strtolower(trim($data['contact_email'])),
        ];
    }
}
