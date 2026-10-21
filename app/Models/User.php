<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\SubscriptionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use HasRoles;
    use Notifiable;
    use SoftDeletes;
    use TwoFactorAuthenticatable;

    /** Roles de despacho con empresas propias o específicas (NUT-RN-003-20 a -24) */
    public const DISPATCH_MANAGER_ROLE = 'jefe_despacho';

    public const DISPATCHER_ROLE = 'despachador';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'is_active',
        'whatsapp_opt_in',
        'all_companies',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'whatsapp_opt_in' => 'boolean',
        'all_companies' => 'boolean',
        'privacy_accepted_at' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Panel de inicio según el rol (login y RedirectIfAuthenticated)
     */
    public function homeUrl(): string
    {
        // Las pantallas del plan Premium solo son inicio si la institución lo tiene
        $premium = fn (string $feature) => app(SubscriptionService::class)->hasFeature($feature, $this);

        return match (true) {
            $this->hasRole('nutricionista') => route('dashboard.nutrition'),
            $this->hasRole('despachador') => route('dashboard.dispatch'),
            // El jefe de nutrición trabaja como nutricionista: inicia en las dietas activas
            $this->hasRole('jefe_nutricion') => route('dashboard.nutrition'),
            // Sin Premium sus pantallas (despachos del día, empresas) están bloqueadas: va a pagar el plan (NUT-RN-003-17)
            $this->hasRole('jefe_despacho') => $premium('dispatch_log') ? route('dispatches.index') : route('subscription.index'),
            $this->hasRole('admin') => route('admin.users.index'),
            // Roles creados desde /admin/roles (p. ej. enfermería): según sus permisos
            $this->can('viewActiveDiets') => route('dashboard.nutrition'),
            $this->can('viewDispatches') && $premium('dispatch_log') => route('dispatches.index'),
            $this->can('viewDiets') && $premium('history') => route('diets.history'),
            $this->can('viewDispatches') || $this->can('viewDiets') => route('subscription.index'),
            default => route('profile.show'),
        };
    }

    /* ===================== SEGURIDAD Y CUMPLIMIENTO ===================== */

    /** Aceptó la versión vigente de la política de tratamiento de datos (config security.privacy) */
    public function hasAcceptedPrivacyPolicy(): bool
    {
        return $this->privacy_accepted_at !== null && $this->privacy_version === config('security.privacy.version');
    }

    /** Su rol exige verificación en dos pasos (config security.two_factor_roles) */
    public function requiresTwoFactor(): bool
    {
        $roles = config('security.two_factor_roles', []);

        return $roles !== [] && $this->hasAnyRole($roles);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /**
     * Si el servidor de correo falla, muestra un error en el formulario en lugar de un 500
     */
    public function sendPasswordResetNotification($token): void
    {
        try {
            parent::sendPasswordResetNotification($token);
        } catch (TransportExceptionInterface $e) {
            report($e);

            throw ValidationException::withMessages([
                'email' => 'No pudimos enviar el correo de recuperación. Inténtalo más tarde o contacta al administrador.',
            ]);
        }
    }

    public function routeNotificationForWhatsApp(): ?string
    {
        return $this->phone;
    }

    public function dietVersions()
    {
        return $this->hasMany(DietHistory::class, 'updated_by');
    }

    /* ===================== EMPRESAS (NUT-RN-003) ===================== */

    /** Empresas asignadas una a una (sin efecto si all_companies) */
    public function companies()
    {
        return $this->belongsToMany(Company::class);
    }

    /**
     * Tiene acceso a todas las empresas, incluidas las que se creen después: admin o «Todas».
     * El jefe de despacho y el despachador nunca: solo sus empresas (NUT-RN-003-22, -24).
     */
    public function hasAllCompanies(): bool
    {
        if ($this->hasRole(\App\Services\UserService::ADMIN_ROLE)) {
            return true;
        }

        return $this->all_companies && ! $this->hasAnyRole([self::DISPATCH_MANAGER_ROLE, self::DISPATCHER_ROLE]);
    }

    /** Jefe de despacho (sin ser admin): administra solo las empresas a su nombre */
    public function isDispatchManager(): bool
    {
        return $this->hasRole(self::DISPATCH_MANAGER_ROLE) && ! $this->hasRole(\App\Services\UserService::ADMIN_ROLE);
    }

    /** Empresas que puede ver (incluye desactivadas, para su historial): «Todas», las suyas (jefe de despacho) o las asignadas */
    public function accessibleCompanies()
    {
        if ($this->hasAllCompanies()) {
            return Company::query();
        }

        return Company::where(fn ($q) => $q
            ->whereHas('users', fn ($q) => $q->whereKey($this->getKey()))
            ->when($this->isDispatchManager(), fn ($q) => $q->orWhere('manager_id', $this->getKey())));
    }

    /**
     * Despachador sin ninguna empresa específica activa: no puede ver ninguna pantalla (NUT-RN-003-24).
     * Sin Premium todos trabajan en la predeterminada, así que no se exige (NUT-RN-003-18, -19).
     */
    public function lacksRequiredCompany(): bool
    {
        return $this->hasMultipleCompanies()
            && $this->hasRole(self::DISPATCHER_ROLE)
            && ! $this->hasRole(\App\Services\UserService::ADMIN_ROLE)
            && ! $this->companies()->where('is_active', true)->exists();
    }

    /**
     * Empresas que puede elegir en el selector: las accesibles y activas. Sin Premium solo la
     * predeterminada: los datos de las demás se conservan hasta renovar (NUT-RN-003-18, -19).
     */
    public function selectableCompanies()
    {
        if (! $this->hasMultipleCompanies()) {
            return Company::where('is_default', true)->active();
        }

        return $this->accessibleCompanies()->active()->orderBy('name');
    }

    /** Trabajar con varias empresas es función Premium; el admin la tiene siempre (NUT-RN-003-16) */
    public function hasMultipleCompanies(): bool
    {
        return app(SubscriptionService::class)->hasFeature('companies', $this);
    }

    public function canAccessCompany(Company|int $company): bool
    {
        return $this->accessibleCompanies()->whereKey($company instanceof Company ? $company->getKey() : $company)->exists();
    }
}
