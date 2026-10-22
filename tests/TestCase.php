<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // La aceptación de la política y el 2FA obligatorio se prueban en ComplianceTest, y los dominios
        // de correo en EmailDomainsTest; en el resto de pruebas los usuarios del seeder no los tienen.
        config([
            'security.privacy.required' => false,
            'security.two_factor_roles' => [],
            'security.force_https' => false,
            'security.seed_admin_email_domain' => false,
        ]);
    }

    /** Plan Premium vigente para la institución (amplio para que travelTo no lo deje fuera) */
    protected function activatePremium(): void
    {
        \App\Models\Subscription::create([
            'plan' => 'premium',
            'period' => 'annual',
            'starts_at' => '2000-01-01 00:00:00',
            'ends_at' => '2100-01-01 00:00:00',
        ]);
        app(\App\Services\SubscriptionService::class)->forget();
    }
}
