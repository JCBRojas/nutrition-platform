<?php

namespace App\Http\Controllers;

use App\Events\PacienteActualizado;
use App\Models\Paciente;
use Illuminate\Http\Request;

class PacienteController extends Controller
{
    public function savePaciente(Request $request)
    {
        // Validar los datos recibidos
        // $validatedData = $request->validate([
        //     'habitacion' => 'nullable|string',
        //     'nombre' => 'nullable|string',
        //     'tiempo_comida' => 'nullable|string',
        //     'consistencia' => 'nullable|string',
        //     'especificaciones' => 'nullable|string',
        //     'observaciones' => 'nullable|string',
        //     'aislamiento' => 'nullable|boolean',
        //     'cambios' => 'nullable|string',
        // ]);
        // dd($request->all());
        $validatedData = $request->validate([
            'habitation' => 'nullable|string',
            'namePatient' => 'nullable|string',
            'timeFood' => 'nullable|array',
            'consistency' => 'nullable|array',
            'specifications' => 'nullable|array',
            'observations' => 'nullable|string',
            'isolation' => 'nullable|string',
            'changes' => 'nullable|string',
        ]);
        // Guardar el paciente en la base de datos
        $paciente = Paciente::create([
            'habitation'     => $request->input('habitation'),
            'namePatient'    => $request->input('namePatient'),

            // ARRAYS (select multiple / checkbox)
            'timeFood'       => json_encode($request->input('timeFood', [])),
            'consistency'    => json_encode($request->input('consistency', [])),
            'specifications' => json_encode($request->input('specifications', [])),

            // STRINGS
            'observations'   => $request->input('observations'),
            'isolation'      => $request->input('isolation'),
            'changes'        => $request->input('changes'),
            'confirmed'        => $request->input('confirmed', false),
        ]);

        // event( new PacienteActualizado($paciente) );
        // event($event); // Emitir el evento para notificar a los clientes conectados

        // Retornar una respuesta JSON con el paciente creado
        return redirect()->back();
        // return response()->json($paciente, 201);

    }
}
