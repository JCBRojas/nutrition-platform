<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use HasRoles;
    use Notifiable;
    use SoftDeletes;
    use TwoFactorAuthenticatable;

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
        return match (true) {
            $this->hasRole('nutricionista') => route('dashboard.nutrition'),
            $this->hasRole('despachador') => route('dashboard.dispatch'),
            $this->hasRole('jefe_nutricion') => route('prices.index'),
            $this->hasRole('admin') => route('admin.users.index'),
            // Roles creados desde /admin/roles (p. ej. enfermería): según sus permisos
            $this->can('viewActiveDiets') => route('dashboard.nutrition'),
            $this->can('viewDispatches') => route('dispatches.index'),
            $this->can('viewDiets') => route('diets.history'),
            default => route('profile.show'),
        };
    }

    public function routeNotificationForWhatsApp(): ?string
    {
        return $this->phone;
    }

    public function dietVersions()
    {
        return $this->hasMany(DietHistory::class, 'updated_by');
    }
}
