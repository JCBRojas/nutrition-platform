<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDietRequest;
use App\Http\Requests\StoreDietVersionRequest;
use App\Models\Diet;
use App\Services\DietService;

class DietController extends Controller
{

    public function __construct(private DietService $dietService){}

    
    /**
     * Crea una nueva dieta para el usuario autenticado.
     *
     * @param StoreDietRequest $request La solicitud validada con los datos de la dieta
     * @return \Illuminate\Http\RedirectResponse Redirige a la página anterior con mensaje de éxito
     */
    public function createDiet(StoreDietRequest $request)
    {
       $diet = $this->dietService->createDiet($request->validated(), auth()->id());
        return redirect()->back()->with('success', 'Dieta creada');

    }

    /**
     * Edita o crea una nueva versión de dieta
     *
     * Este método permite editar una dieta existente o crear una nueva versión
     * de la misma, permitiendo mantener un historial de cambios en las dietas
     * de los usuarios.
     *
     */
    public function createNewVersionDiet(StoreDietVersionRequest $request, Diet $diet)
    {        
        $this->dietService->createNewVersion($diet, $request->validated(), auth()->id());
        return redirect()->back()->with('success', 'Dieta actualizada');
    }
}
