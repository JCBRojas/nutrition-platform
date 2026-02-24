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

        // Guardar el paciente en la base de datos
        $paciente = Paciente::create($request->all());

        event( new PacienteActualizado($paciente) );
        // event($event); // Emitir el evento para notificar a los clientes conectados

        // Retornar una respuesta JSON con el paciente creado
        return redirect()->back();
        // return response()->json($paciente, 201);

    }
}
