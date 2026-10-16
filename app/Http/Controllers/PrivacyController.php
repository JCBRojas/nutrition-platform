<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\Request;

/**
 * Política de tratamiento de datos (Ley 1581 de 2012): página pública y aceptación al ingresar.
 */
class PrivacyController extends Controller
{
    public function show()
    {
        return view('legal.privacy');
    }

    public function acceptForm(Request $request)
    {
        if ($request->user()->hasAcceptedPrivacyPolicy()) {
            return redirect()->to($request->user()->homeUrl());
        }

        return view('legal.privacy-accept');
    }

    public function accept(Request $request)
    {
        $request->validate(['accept' => ['accepted']], ['accept.accepted' => 'Debes marcar la casilla para aceptar la política.']);

        $user = $request->user();
        $user->forceFill(['privacy_accepted_at' => now(), 'privacy_version' => config('security.privacy.version')])->save();
        AuditLogger::log('privacy_accepted', "Aceptó la política de datos versión {$user->privacy_version}", $user, ['version' => $user->privacy_version]);

        return redirect()->to($user->homeUrl());
    }
}
