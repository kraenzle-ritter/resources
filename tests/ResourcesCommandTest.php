<?php

namespace KraenzleRitter\Resources\Tests;

use KraenzleRitter\Resources\Tests\TestCase;

class ResourcesCommandTest extends TestCase
{
    public function test_command_requires_provider_option()
    {
        $this->artisan('resources:fetch')
            ->expectsOutput('A provider is required')
            ->assertExitCode(1);
    }

    public function test_command_validates_provider_option()
    {
        $this->artisan('resources:fetch --provider=invalid')
            ->expectsOutputToContain('provider only gnd, wikidata, wikipedia or metagrid allowed')
            ->assertExitCode(3);
    }

    public function test_command_accepts_valid_providers()
    {
        $validProviders = ['gnd', 'wikidata', 'wikipedia', 'metagrid'];

        foreach ($validProviders as $provider) {
            // Da wir keine Modelle mit diesem Provider haben, wird der Command erfolgreich laufen
            // aber nichts verarbeiten
            $this->artisan("resources:fetch --provider={$provider}")
                ->assertExitCode(0);
        }
    }
}
