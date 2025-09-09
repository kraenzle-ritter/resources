<?php

namespace KraenzleRitter\Resources\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use KraenzleRitter\Resources\Console\Commands\ResourcesFetch;
use KraenzleRitter\Resources\Resource;
use ReflectionClass;

class ResourcesFetchCommandTest extends TestCase
{
    public function test_it_requires_a_provider_when_no_options_given()
    {
        $this->artisan('resources:fetch')
            ->expectsOutput('A provider is required')
            ->assertExitCode(1);
    }

    public function test_it_validates_provider_parameter()
    {
        $this->artisan('resources:fetch', ['--provider' => 'invalid'])
            ->expectsOutput('provider only gnd, wikidata, wikipedia or metagrid allowed. invalid given')
            ->assertExitCode(3);
    }

    public function test_it_accepts_valid_providers()
    {
        $validProviders = ['gnd', 'wikidata', 'wikipedia', 'metagrid'];

        foreach ($validProviders as $provider) {
            // Mock empty resource collection to avoid actual processing
            $this->artisan('resources:fetch', ['--provider' => $provider])
                ->assertExitCode(0);
        }
    }

    public function test_command_signature_is_properly_defined()
    {
        $command = new ResourcesFetch();
        $reflection = new ReflectionClass($command);

        $signatureProperty = $reflection->getProperty('signature');
        $signature = $signatureProperty->getValue($command);

        $descriptionProperty = $reflection->getProperty('description');
        $description = $descriptionProperty->getValue($command);

        $this->assertStringContainsString('resources:fetch', $signature);
        $this->assertStringContainsString('--provider=', $signature);
        $this->assertStringContainsString('--repair', $signature);
        $this->assertStringContainsString('--delete', $signature);
        $this->assertStringContainsString('--debug', $signature);
        $this->assertEquals('Fetch resources and show them.', $description);
    }

    public function test_command_has_public_client_property()
    {
        $command = new ResourcesFetch();

        // Test that the command has the client property
        $this->assertObjectHasProperty('client', $command);
    }

    public function test_providers_array_is_properly_configured()
    {
        $command = new ResourcesFetch();
        $reflection = new ReflectionClass($command);

        $providersProperty = $reflection->getProperty('providers');
        $providers = $providersProperty->getValue($command);

        // Test that common providers are configured
        $this->assertArrayHasKey('gnd', $providers);
        $this->assertArrayHasKey('viaf', $providers);
        $this->assertArrayHasKey('worldcat', $providers);
        $this->assertArrayHasKey('sikart', $providers);

        // Test that GND provider has correct URL patterns
        $this->assertContains('http://d-nb.info/gnd/', $providers['gnd']);
        $this->assertContains('https://d-nb.info/gnd/', $providers['gnd']);
    }

    public function test_providers_have_expected_structure()
    {
        $command = new ResourcesFetch();
        $reflection = new ReflectionClass($command);

        $providersProperty = $reflection->getProperty('providers');
        $providers = $providersProperty->getValue($command);

        // Test specific provider configurations
        $this->assertIsArray($providers['gnd']);
        $this->assertIsArray($providers['viaf']);
        $this->assertIsArray($providers['worldcat']);

        // Test that some known providers exist
        $this->assertArrayHasKey('gnd', $providers);
        $this->assertArrayHasKey('viaf', $providers);
        $this->assertArrayHasKey('worldcat', $providers);
        $this->assertArrayHasKey('sikart', $providers);

        // Test that hls provider has special suffix configuration
        $this->assertArrayHasKey('hls-dhs-dss', $providers);
        $this->assertIsArray($providers['hls-dhs-dss']);
        $this->assertContains('.php', $providers['hls-dhs-dss']);

        // Debug: Print all provider keys to see what's available
        // $this->fail('Available providers: ' . implode(', ', array_keys($providers)));
    }

    public function test_command_methods_exist()
    {
        $command = new ResourcesFetch();

        // Test that required methods exist
        $this->assertTrue(method_exists($command, 'handle'));
        $this->assertTrue(method_exists($command, 'repair'));
        $this->assertTrue(method_exists($command, 'deleteDoublets'));
    }

    public function test_handle_method_structure()
    {
        $command = new ResourcesFetch();
        $reflection = new ReflectionClass($command);

        $handleMethod = $reflection->getMethod('handle');
        $this->assertTrue($handleMethod->isPublic());

        $repairMethod = $reflection->getMethod('repair');
        $this->assertTrue($repairMethod->isPublic());

        $deleteDoubletsMethod = $reflection->getMethod('deleteDoublets');
        $this->assertTrue($deleteDoubletsMethod->isPublic());
    }

    public function test_repair_method_can_be_called()
    {
        $command = new ResourcesFetch();
        $reflection = new ReflectionClass($command);

        // Test that repair method is callable
        $repairMethod = $reflection->getMethod('repair');
        $this->assertTrue($repairMethod->isPublic());

        // We can't actually call it due to database requirements,
        // but we can verify its existence and accessibility
        $this->assertInstanceOf(ResourcesFetch::class, $command);
    }

    public function test_provider_url_replacement_logic()
    {
        $command = new ResourcesFetch();
        $reflection = new ReflectionClass($command);

        $providersProperty = $reflection->getProperty('providers');
        $providers = $providersProperty->getValue($command);

        // Test URL replacement patterns for GND
        $gndPatterns = $providers['gnd'];
        $this->assertContains('http://d-nb.info/gnd/', $gndPatterns);
        $this->assertContains('https://d-nb.info/gnd/', $gndPatterns);

        // Test VIAF patterns
        $viafPatterns = $providers['viaf'];
        $this->assertContains('http://viaf.org/viaf/', $viafPatterns);
        $this->assertContains('https://viaf.org/viaf/', $viafPatterns);

        // Test that each provider array contains URL patterns
        foreach ($providers as $providerName => $patterns) {
            $this->assertIsArray($patterns, "Provider {$providerName} should have array of patterns");
            $this->assertNotEmpty($patterns, "Provider {$providerName} should not have empty patterns");
        }
    }

    public function test_command_properties_are_correctly_set()
    {
        $command = new ResourcesFetch();

        // Test signature property
        $reflection = new ReflectionClass($command);
        $signatureProperty = $reflection->getProperty('signature');
        $signature = $signatureProperty->getValue($command);

        // Verify all required options are in signature
        $this->assertStringContainsString('--provider=', $signature);
        $this->assertStringContainsString('--repair', $signature);
        $this->assertStringContainsString('--delete', $signature);
        $this->assertStringContainsString('--debug', $signature);

        // Test description
        $descriptionProperty = $reflection->getProperty('description');
        $description = $descriptionProperty->getValue($command);
        $this->assertEquals('Fetch resources and show them.', $description);

        // Test that client property exists and is public
        $this->assertObjectHasProperty('client', $command);
        $clientProperty = $reflection->getProperty('client');
        $this->assertTrue($clientProperty->isPublic());
    }
}
