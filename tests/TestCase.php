<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // La aceptación de la política y el 2FA obligatorio se prueban en ComplianceTest;
        // en el resto de pruebas los usuarios del seeder no los tienen.
        config(['security.privacy.required' => false, 'security.two_factor_roles' => [], 'security.force_https' => false]);
    }
}
