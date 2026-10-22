<?php

namespace App\Http\Controllers;

use App\Models\DemoRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Formulario público «Solicitar demo». Protegido con limitación de envíos (ruta) y un campo
 * trampa invisible para bots (`website`): si viene lleno, se responde como si se hubiera
 * guardado, sin guardar nada.
 */
class DemoRequestController extends Controller
{
    public function create()
    {
        return view('auth.demo-request');
    }

    public function store(Request $request)
    {
        if (filled($request->input('website'))) {
            return redirect()->route('demo.create')->with('status', $this->thanks());
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'institution' => ['required', 'string', 'max:160'],
            'position' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[+0-9 ()-]{7,30}$/'],
            'beds' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'message' => ['nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
        ], [
            'name.required' => 'Escribe tu nombre.',
            'institution.required' => 'Escribe el nombre de la institución.',
            'phone.required' => 'Escribe un teléfono de contacto.',
            'phone.regex' => 'Escribe un teléfono válido, por ejemplo +57 300 123 4567.',
            'beds.integer' => 'Escribe el número de camas en cifras, por ejemplo 120.',
            'beds.min' => 'Escribe un número de camas de 1 o más.',
            'consent.accepted' => 'Debes autorizar el uso de tus datos para que te contactemos.',
        ]);

        unset($data['consent']);
        $demo = DemoRequest::create($data + ['status' => 'new', 'ip' => $request->ip()]);

        // Aviso al equipo comercial; si el correo falla, la solicitud ya quedó guardada
        rescue(fn () => Mail::raw(
            "Nueva solicitud de demo #{$demo->id}\n\nNombre: {$demo->name}\nInstitución: {$demo->institution}\nCargo: ".($demo->position ?: '—')
            ."\nCorreo: {$demo->email}\nTeléfono: {$demo->phone}\nCamas: ".($demo->beds ?: '—')."\n\nMensaje:\n".($demo->message ?: '—'),
            fn ($m) => $m->to(config('landing.demo_notify_email'))->replyTo($demo->email, $demo->name)->subject("Solicitud de demo: {$demo->institution}")
        ));

        return redirect()->route('demo.create')->with('status', $this->thanks());
    }

    private function thanks(): string
    {
        return '¡Gracias! Recibimos tu solicitud. Te contactaremos en máximo 2 días hábiles para agendar la demo.';
    }
}
