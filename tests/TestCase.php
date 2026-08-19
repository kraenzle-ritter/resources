<?php

namespace KraenzleRitter\Resources\Tests;

use Illuminate\Database\Schema\Blueprint;
use KraenzleRitter\Resources\ResourcesServiceProvider;
use KraenzleRitter\Resources\Tests\Support\MockProviderClient;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();

        // Tests in the live-api group exist to talk to the real services, so
        // they must not get the fixture-backed clients.
        if (! in_array('live-api', $this->groups(), true)) {
            $this->fakeAllProviders();
        }
    }

    /**
     * Bind every provider API client to a fixture-backed client, so no test can
     * reach a live API by accident. Individual tests rebind what they need.
     *
     * Note: ResourceSyncService still builds its own Guzzle clients for the
     * Wikidata/SPARQL calls and is not covered here yet — see task group 7.
     */
    protected function fakeAllProviders(): void
    {
        foreach ([
            \KraenzleRitter\Resources\Gnd::class => 'gnd',
            \KraenzleRitter\Resources\Geonames::class => 'geonames',
            \KraenzleRitter\Resources\Idiotikon::class => 'idiotikon',
            \KraenzleRitter\Resources\Ortsnamen::class => 'ortsnamen',
            \KraenzleRitter\Resources\Metagrid::class => 'metagrid',
            \KraenzleRitter\Resources\Wikidata::class => 'wikidata',
            \KraenzleRitter\Resources\Wikipedia::class => 'wikipedia',
        ] as $clientClass => $provider) {
            $this->fakeProvider($clientClass, $provider);
        }

        $this->fakeAntonProvider();
        $this->fakeSyncService();
    }

    /**
     * Components call syncFromProvider() after a save, which otherwise reaches
     * Wikidata. Bound to a service whose HTTP client answers with an empty JSON
     * document, so saves stay offline. ResourceSyncCacheTest drives the real
     * behaviour with purpose-built responses.
     */
    protected function fakeSyncService(): void
    {
        $this->app->bind(
            \KraenzleRitter\Resources\ResourceSyncService::class,
            fn ($app, array $params = []) => new \KraenzleRitter\Resources\ResourceSyncService(
                $params['filter'] ?? [],
                (new MockProviderClient(array_map(
                    fn () => new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/json'], '{}'),
                    range(1, 20)
                )))->client
            )
        );
    }

    protected function setUpDatabase()
    {
        $schema = $this->app['db']->connection()->getSchemaBuilder();

        // Test Models Tabelle erstellen
        $schema->create('test_models', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->timestamps();
        });

        // Resources Tabelle erstellen
        $schema->create('resources', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('provider_id');
            $table->string('url');
            $table->json('full_json')->nullable();
            $table->morphs('resourceable');
            $table->timestamps();
        });
    }

    /**
     * Bind a provider API client backed by fixtures into the container, so the
     * Livewire components resolve it instead of talking to the live API.
     *
     *     $mock = $this->fakeProvider(Gnd::class, 'gnd', 'search');
     *
     * @param class-string $clientClass
     */
    protected function fakeProvider(string $clientClass, string $provider, string $fixture = 'search'): MockProviderClient
    {
        $mock = MockProviderClient::repeating($provider, $fixture);

        $this->app->bind($clientClass, fn () => new $clientClass($mock->client));

        return $mock;
    }

    /**
     * Anton takes its provider key as a constructor argument, so the components
     * resolve it with makeWith() and the binding has to accept those parameters.
     */
    protected function fakeAntonProvider(string $fixture = 'search'): MockProviderClient
    {
        $mock = MockProviderClient::repeating('anton', $fixture);

        $this->app->bind(
            \KraenzleRitter\Resources\Anton::class,
            fn ($app, array $params = []) => new \KraenzleRitter\Resources\Anton(
                $params['providerKey'] ?? 'georgfischer',
                $mock->client
            )
        );

        return $mock;
    }

    protected function getPackageProviders($app)
    {
        return [
            ResourcesServiceProvider::class,
            \Livewire\LivewireServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Load environment variables from .env file if it exists
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $env = file_get_contents($envFile);
            $lines = explode("\n", $env);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line) && strpos($line, '=') !== false && !str_starts_with($line, '#')) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    // Set both in $_ENV and putenv for Laravel's env() function
                    $_ENV[$key] = $value;
                    putenv("$key=$value");
                }
            }
        }

        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
    }
}
