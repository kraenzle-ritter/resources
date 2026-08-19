<?php

namespace KraenzleRitter\Resources\Tests;

use KraenzleRitter\Resources\Anton;
use KraenzleRitter\Resources\Gnd;
use KraenzleRitter\Resources\Tests\Support\MockProviderClient;
use Orchestra\Testbench\Attributes\DefineEnvironment;

/**
 * The provider detail page threw `Undefined variable $result` — the controller
 * never passed the variables the view reads. It also renders the raw provider
 * config, which for the Anton providers includes an api_token.
 */
class DiagnosticsProviderPageTest extends TestCase
{
    private const TOKEN = 'super-secret-anton-token-42';

    protected function enableDiagnostics($app): void
    {
        $app['config']->set('resources.diagnostics.enabled', true);
    }

    protected function enableDiagnosticsWithSecrets($app): void
    {
        $app['config']->set('resources.diagnostics.enabled', true);
        $app['config']->set('resources.providers.kba.api_token', self::TOKEN);
        $app['config']->set('resources.providers.geonames.user_name', 'secret-geonames-account');
    }

    #[DefineEnvironment('enableDiagnostics')]
    public function test_the_provider_page_renders_for_an_api_provider()
    {
        $this->get('/resources-check/provider/gnd')->assertOk();
    }

    #[DefineEnvironment('enableDiagnostics')]
    public function test_the_provider_page_renders_for_every_configured_api_provider()
    {
        $providers = array_filter(
            config('resources.providers', []),
            fn ($config) => ! empty($config['api-type'])
        );

        foreach (array_keys($providers) as $key) {
            $this->get("/resources-check/provider/{$key}")
                ->assertOk("Provider page for '{$key}' did not render");
        }
    }

    #[DefineEnvironment('enableDiagnostics')]
    public function test_the_provider_page_survives_an_api_failure()
    {
        $this->app->bind(Gnd::class, fn () => new Gnd(MockProviderClient::withStatus(500)->client));

        $this->get('/resources-check/provider/gnd')->assertOk();
    }

    #[DefineEnvironment('enableDiagnostics')]
    public function test_an_unknown_provider_redirects_to_the_index()
    {
        $this->get('/resources-check/provider/does-not-exist')
            ->assertRedirect(route('resources.check.index'));
    }

    #[DefineEnvironment('enableDiagnosticsWithSecrets')]
    public function test_the_provider_page_does_not_leak_an_api_token()
    {
        $response = $this->get('/resources-check/provider/kba');

        $response->assertOk();
        $response->assertDontSee(self::TOKEN, false);
    }

    #[DefineEnvironment('enableDiagnosticsWithSecrets')]
    public function test_the_provider_page_does_not_leak_the_geonames_account()
    {
        $response = $this->get('/resources-check/provider/geonames');

        $response->assertOk();
        $response->assertDontSee('secret-geonames-account', false);
    }

    #[DefineEnvironment('enableDiagnosticsWithSecrets')]
    public function test_the_config_page_does_not_leak_secrets()
    {
        $response = $this->get('/resources-check/config');

        $response->assertOk();
        $response->assertDontSee(self::TOKEN, false);
        $response->assertDontSee('secret-geonames-account', false);
    }

    #[DefineEnvironment('enableDiagnosticsWithSecrets')]
    public function test_the_index_does_not_leak_secrets()
    {
        $response = $this->get('/resources-check');

        $response->assertOk();
        $response->assertDontSee(self::TOKEN, false);
        $response->assertDontSee('secret-geonames-account', false);
    }

    #[DefineEnvironment('enableDiagnostics')]
    public function test_non_secret_configuration_stays_visible()
    {
        $response = $this->get('/resources-check/provider/gnd');

        $response->assertOk();
        $response->assertSee('https://lobid.org/gnd/', false);
        $response->assertSee('https://d-nb.info/gnd/{provider_id}', false);
    }
}
