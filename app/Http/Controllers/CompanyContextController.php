<?php

namespace App\Http\Controllers;

use App\Support\CurrentCompany;
use Illuminate\Http\Request;

/**
 * Cambio de empresa desde el selector de la parte superior (NUT-RN-003-09, -10, -13).
 */
class CompanyContextController extends Controller
{
    public function switch(Request $request, CurrentCompany $current)
    {
        $data = $request->validate(['company_id' => ['required', 'integer']]);
        $company = $current->switch((int) $data['company_id']);

        // Misma pantalla sin la consulta: página 1 y filtros limpios para la nueva empresa
        $back = strtok(url()->previous(), '?') ?: $request->user()->homeUrl();

        return redirect()->to($back)->with('company-switched', "Empresa cambiada a {$company->name}.");
    }
}
