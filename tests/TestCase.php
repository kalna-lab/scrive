<?php

declare(strict_types=1);

namespace KalnaLab\Scrive\Tests;

use KalnaLab\Scrive\ScriveServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ScriveServiceProvider::class];
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.url', 'https://app.test');
        $app['config']->set('scrive.env', 'test');
        $app['config']->set('scrive.auth.env', 'test');
        $app['config']->set('scrive.auth.redirect-path', '/login');
        $app['config']->set('scrive.auth.landing-path', '/');
        $app['config']->set('scrive.auth.failed-path', '/failed');
        $app['config']->set('scrive.auth.reference-text', 'unit-test');
        $app['config']->set('scrive.auth.test.token', 'bearer-token');
        $app['config']->set('scrive.auth.test.base-path', 'https://eid.test.scrive.example/');
        $app['config']->set('scrive.document.env', 'test');
        $app['config']->set('scrive.document.test.api-token', 'api-token');
        $app['config']->set('scrive.document.test.api-secret', 'api-secret');
        $app['config']->set('scrive.document.test.access-token', 'access-token');
        $app['config']->set('scrive.document.test.access-secret', 'access-secret');
        $app['config']->set('scrive.document.test.base-path', 'https://docs.test.scrive.example/');
        $app['config']->set('scrive.document.callback.secret', 'test-callback-secret');
        $app['config']->set('scrive.document.callback.verify_against_api', true);
    }
}
