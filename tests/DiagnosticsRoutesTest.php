<?php

namespace KraenzleRitter\Resources\Tests;

use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\Attributes\DefineEnvironment;

/**
 * The package registers /resources-check* routes in every host application.
 * They render provider configuration, so they must not exist unless the
 * application asks for them.
 */
class DiagnosticsRoutesTest extends TestCase
{
    /**
     * The provider decides at boot time whether to register the routes, so the
     * config has to be in place before the application boots. WithConfig is
     * applied after boot and would be too late.
     */
    protected function enableDiagnostics($app): void
    {
        $app['config']->set('resources.diagnostics.enabled', true);
    }

    protected function enableDiagnosticsBehindAuth($app): void
    {
        $app['config']->set('resources.diagnostics.enabled', true);
        $app['config']->set('resources.diagnostics.middleware', ['web', 'auth']);
    }

    public function test_the_routes_do_not_exist_by_default()
    {
        $this->assertFalse(
            Route::has('resources.check.index'),
            'Installing the package must not add diagnostics routes'
        );

        $this->get('/resources-check')->assertNotFound();
        $this->get('/resources-check/config')->assertNotFound();
        $this->get('/resources-check/provider/gnd')->assertNotFound();
    }

    #[DefineEnvironment('enableDiagnostics')]
    public function test_the_index_renders_when_enabled()
    {
        $this->assertTrue(Route::has('resources.check.index'));

        $this->get('/resources-check')->assertOk();
    }

    #[DefineEnvironment('enableDiagnostics')]
    public function test_the_config_page_renders_when_enabled()
    {
        $this->get('/resources-check/config')->assertOk();
    }

    #[DefineEnvironment('enableDiagnostics')]
    public function test_the_default_middleware_is_web()
    {
        $middleware = Route::getRoutes()->getByName('resources.check.index')->gatherMiddleware();

        $this->assertContains('web', $middleware);
    }

    #[DefineEnvironment('enableDiagnosticsBehindAuth')]
    public function test_the_configured_middleware_is_applied()
    {
        $middleware = Route::getRoutes()->getByName('resources.check.index')->gatherMiddleware();

        $this->assertContains('web', $middleware);
        $this->assertContains('auth', $middleware, 'An application must be able to require authentication');
    }
}
