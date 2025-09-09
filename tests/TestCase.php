<?php

namespace KraenzleRitter\Resources\Tests;

use Illuminate\Database\Schema\Blueprint;
use KraenzleRitter\Resources\ResourcesServiceProvider;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();
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
