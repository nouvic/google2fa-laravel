<?php

namespace Nouvic\Google2FALaravel\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Nouvic\Google2FALaravel\ServiceProvider as Google2FALaravelServiceProvider;

class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            Google2FALaravelServiceProvider::class,
        ];
    }
}
