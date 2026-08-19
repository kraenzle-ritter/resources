<?php

namespace KraenzleRitter\Resources\Tests;

use Illuminate\Console\Command;
use KraenzleRitter\Resources\Gnd;
use KraenzleRitter\Resources\Tests\Support\MockProviderClient;

/**
 * The command used to check class_exists(ucfirst($providerKey)) and nothing
 * else — never instantiating anything, never calling search(), always
 * returning SUCCESS. That derivation cannot resolve the keys that actually
 * carry an api-type: georgfischer/gosteli/kba all map to the Anton class,
 * manual-input and wikipedia-de are not class names at all.
 */
class TestResourcesCommandTest extends TestCase
{
    public function test_no_provider_is_reported_as_a_missing_class()
    {
        $this->artisan('resources:test-resources')
            ->doesntExpectOutputToContain('nicht gefunden')
            ->doesntExpectOutputToContain('not found');
    }

    public function test_providers_sharing_the_anton_client_are_reached()
    {
        $output = $this->runCommand();

        foreach (['georgfischer', 'gosteli', 'kba'] as $provider) {
            $this->assertStringContainsString($provider, $output, "{$provider} was not reported");
        }
    }

    public function test_language_specific_wikipedia_providers_are_reached()
    {
        $this->assertStringContainsString('wikipedia-de', $this->runCommand());
    }

    public function test_manual_input_is_skipped_not_failed()
    {
        $output = $this->runCommand();

        $this->assertMatchesRegularExpression('/manual-input.*(skipped|übersprungen)/i', $output);
    }

    public function test_a_single_provider_can_be_tested()
    {
        $output = $this->runCommand(['--provider' => 'gnd']);

        $this->assertStringContainsString('gnd', $output);
        $this->assertStringNotContainsString('idiotikon', $output);
    }

    public function test_an_unknown_provider_fails()
    {
        $this->artisan('resources:test-resources', ['--provider' => 'does-not-exist'])
            ->assertExitCode(Command::FAILURE);
    }

    public function test_all_healthy_providers_exit_zero()
    {
        $this->artisan('resources:test-resources', ['--provider' => 'gnd'])
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_a_failing_provider_exits_non_zero()
    {
        // A client that answers 500 for every request.
        $this->app->bind(Gnd::class, fn () => new Gnd(MockProviderClient::withStatus(500)->client));

        $this->artisan('resources:test-resources', ['--provider' => 'gnd'])
            ->assertExitCode(Command::FAILURE);
    }

    public function test_json_output_is_machine_readable()
    {
        $output = $this->runCommand(['--provider' => 'gnd', '--json' => true]);

        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded, "Expected JSON, got: {$output}");
        $this->assertArrayHasKey('providers', $decoded);
        $this->assertArrayHasKey('gnd', $decoded['providers']);
        $this->assertArrayHasKey('status', $decoded['providers']['gnd']);
    }

    private function runCommand(array $arguments = []): string
    {
        // Artisan::call() captures the output buffer; artisan()->run() does not.
        \Illuminate\Support\Facades\Artisan::call('resources:test-resources', $arguments);

        return \Illuminate\Support\Facades\Artisan::output();
    }
}
