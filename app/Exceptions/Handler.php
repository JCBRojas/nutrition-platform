<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (ValidationException $e, Request $request) {
            if (! $request->isMethod('post') || ! $request->is('login')) {
                return null;
            }

            $errors = $e->errors();

            if (empty($errors)) {
                return null;
            }

            $message = is_array($errors[0] ?? null) ? reset($errors[0]) : ($errors[Fortify::username()] ?? null);

            if (! is_string($message) || ! str_contains(strtolower($message), 'demasiados intentos')) {
                return null;
            }

            return redirect()
                ->route('login')
                ->withErrors($errors)
                ->withInput($request->only('email', 'remember'));
        });
    }
}
