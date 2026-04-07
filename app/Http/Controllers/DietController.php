<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDietRequest;
use App\Http\Requests\StoreDietVersionRequest;
use App\Models\Diet;
use App\Services\DietService;
use Illuminate\Support\Facades\DB;

class DietController extends Controller
{

    public function __construct(private DietService $dietService) {}


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


    public function index()
{

/*
-----------------------------
PRODUCCIÓN DE COCINA HOY
-----------------------------
*/

$kitchenToday = DB::select("
SELECT

tipo_dieta,

SUM(tiempo_comida='desayuno') AS desayuno,
SUM(tiempo_comida='media_manana') AS media_manana,
SUM(tiempo_comida='almuerzo') AS almuerzo,
SUM(tiempo_comida='algo') AS algo,
SUM(tiempo_comida='merienda') AS merienda,
SUM(tiempo_comida='cena') AS cena

FROM diet_meal_report_view

WHERE DATE(created_at)=CURDATE()

GROUP BY tipo_dieta
ORDER BY tipo_dieta
");


/*
-----------------------------
TOTALES GLOBALES
-----------------------------
*/

$global = DB::select("
SELECT

tipo_dieta,

SUM(tiempo_comida='desayuno') AS desayuno,
SUM(tiempo_comida='media_manana') AS media_manana,
SUM(tiempo_comida='almuerzo') AS almuerzo,
SUM(tiempo_comida='algo') AS algo,
SUM(tiempo_comida='merienda') AS merienda,
SUM(tiempo_comida='cena') AS cena,
SUM(tiempo_comida='fraccionada') AS fraccionada,

COUNT(*) AS total_bandejas

FROM diet_meal_report_view

WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())

GROUP BY tipo_dieta
");


/*
-----------------------------
PRODUCCIÓN MENSUAL
-----------------------------
*/

$monthly = DB::select("
SELECT

DATE_FORMAT(created_at,'%Y-%m') AS mes,

SUM(tiempo_comida='desayuno') AS desayuno,
SUM(tiempo_comida='media_manana') AS media_manana,
SUM(tiempo_comida='almuerzo') AS almuerzo,
SUM(tiempo_comida='algo') AS algo,
SUM(tiempo_comida='merienda') AS merienda,
SUM(tiempo_comida='cena') AS cena

FROM diet_meal_report_view

GROUP BY mes
ORDER BY mes DESC
");


return view('reports-diets',[
'kitchenToday'=>$kitchenToday,
'global'=>$global,
'monthly'=>$monthly
]);

}


public function nutritionReport()
{

/*
--------------------------------
BASE QUERY (última versión dieta)
--------------------------------
*/

$baseQuery = "
SELECT 
d.created_at,

JSON_CONTAINS(dv.timeFood,'\"desayuno\"') AS desayuno,
JSON_CONTAINS(dv.timeFood,'\"media_manana\"') AS media_manana,
JSON_CONTAINS(dv.timeFood,'\"almuerzo\"') AS almuerzo,
JSON_CONTAINS(dv.timeFood,'\"algo\"') AS algo,
JSON_CONTAINS(dv.timeFood,'\"cena\"') AS cena,
JSON_CONTAINS(dv.timeFood,'\"fraccionada\"') AS fraccionada

FROM diet_histories dv
JOIN diets d ON d.id = dv.diet_id

WHERE dv.version = (
    SELECT MAX(version)
    FROM diet_histories
    WHERE diet_id = dv.diet_id
)
AND d.status = true
";



/*
--------------------------------
TOTALES DIARIOS
--------------------------------
*/

$dailyTotals = DB::select("
SELECT

DATE(created_at) AS fecha,

SUM(desayuno) AS desayuno,
SUM(media_manana) AS media_manana,
SUM(almuerzo) AS almuerzo,
SUM(algo) AS algo,
SUM(cena) AS cena,
SUM(fraccionada) AS fraccionada

FROM ($baseQuery) t

where MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
GROUP BY fecha
ORDER BY fecha DESC
");



/*
--------------------------------
TOTALES MENSUALES
--------------------------------
*/

$monthlyTotals = DB::select("
SELECT

DATE_FORMAT(created_at,'%Y-%m') AS mes,

SUM(desayuno) AS desayuno,
SUM(media_manana) AS media_manana,
SUM(almuerzo) AS almuerzo,
SUM(algo) AS algo,
SUM(cena) AS cena,
SUM(fraccionada) AS fraccionada

FROM ($baseQuery) t

GROUP BY mes
ORDER BY mes DESC
");



/*
--------------------------------
TOTALES GLOBALES
--------------------------------
*/

$globalTotals = DB::selectOne("
SELECT

SUM(desayuno) AS desayuno,
SUM(almuerzo) AS almuerzo,
SUM(cena) AS cena,
SUM(fraccionada) AS fraccionada

FROM ($baseQuery) t
");



/*
--------------------------------
DATOS PARA GRÁFICOS
--------------------------------
*/

$chartLabels = array_column($monthlyTotals,'mes');
$chartBreakfast = array_column($monthlyTotals,'desayuno');
$chartLunch = array_column($monthlyTotals,'almuerzo');
$chartDinner = array_column($monthlyTotals,'cena');



return view('reports-diets-nutricion',[
'dailyTotals'=>$dailyTotals,
'monthlyTotals'=>$monthlyTotals,
'globalTotals'=>$globalTotals,
'chartLabels'=>$chartLabels,
'chartBreakfast'=>$chartBreakfast,
'chartLunch'=>$chartLunch,
'chartDinner'=>$chartDinner
]);

}

    // end 
}
