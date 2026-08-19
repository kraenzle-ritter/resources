<?php
namespace KraenzleRitter\Resources\Tests;

use Orchestra\Testbench\Attributes\DefineEnvironment;

class IdRefDiagnosticsTest extends TestCase
{
    protected function enableDiagnostics($app): void
    {
        $app['config']->set('resources.diagnostics.enabled', true);
    }

    #[DefineEnvironment('enableDiagnostics')]
    public function test_the_diagnostics_page_renders_for_idref()
    {
        $this->get('/resources-check/provider/idref')->assertOk();
    }

    public function test_the_artisan_command_reports_idref()
    {
        \Illuminate\Support\Facades\Artisan::call('resources:test-resources', ['--provider' => 'idref']);

        $this->assertStringContainsString('idref', \Illuminate\Support\Facades\Artisan::output());
    }
}
